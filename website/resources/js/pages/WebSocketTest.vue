<template>
    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6 text-slate-800 dark:text-slate-200">
            WebSocket Test
        </h1>

        <!-- Connection status -->
        <div class="mb-6 p-4 rounded-xl border" :class="statusClasses">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full" :class="dotClasses"></div>
                <div>
                    <p class="font-semibold">{{ statusLabel }}</p>
                    <p class="text-sm opacity-75">{{ statusDetail }}</p>
                </div>
            </div>
        </div>

        <!-- Config info -->
        <div class="mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold mb-3 text-slate-600 dark:text-slate-400 uppercase tracking-wide">Configuration</h2>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <span class="text-slate-500">Host:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.host }}</span>
                <span class="text-slate-500">Port:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.port }}</span>
                <span class="text-slate-500">Key:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.key || '(empty)' }}</span>
                <span class="text-slate-500">TLS:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.tls ? 'Yes (wss://)' : 'No (ws://)' }}</span>
                <span class="text-slate-500">Protocol:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.tls ? 'wss' : 'ws' }}://{{ config.host }}:{{ config.port }}</span>
                <span class="text-slate-500">Auth token:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ config.hasAuth ? 'Bearer token' : config.hasGuest ? 'Guest token' : 'None' }}</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2 mb-6">
            <Button
                label="Connect"
                severity="success"
                size="small"
                :disabled="connectionState === 'connected'"
                @click="connect"
            />
            <Button
                label="Disconnect"
                severity="danger"
                size="small"
                :disabled="connectionState === 'disconnected'"
                @click="handleDisconnect"
            />
            <Button
                label="Clear log"
                severity="secondary"
                size="small"
                variant="outlined"
                @click="logEntries = []"
            />
        </div>

        <!-- Channel test -->
        <div class="mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold mb-3 text-slate-600 dark:text-slate-400 uppercase tracking-wide">Channel Test</h2>
            <div class="flex gap-2 mb-3">
                <InputText
                    v-model="channelCode"
                    placeholder="Game code (e.g. ABCDEF)"
                    class="flex-1 font-mono uppercase"
                    size="small"
                />
                <Button
                    :label="subscribedChannel ? 'Leave' : 'Join'"
                    :severity="subscribedChannel ? 'danger' : 'success'"
                    size="small"
                    :disabled="connectionState !== 'connected' || (!channelCode && !subscribedChannel)"
                    @click="subscribedChannel ? leaveChannel() : joinChannel()"
                />
            </div>
            <p v-if="subscribedChannel" class="text-sm text-emerald-600 dark:text-emerald-400">
                Subscribed to: <span class="font-mono">presence-game.{{ subscribedChannel }}</span>
            </p>
            <div v-if="presenceMembers.length > 0" class="mt-2">
                <p class="text-xs text-slate-500 mb-1">Members ({{ presenceMembers.length }}):</p>
                <div class="flex flex-wrap gap-1">
                    <span v-for="m in presenceMembers" :key="m.id" class="text-xs px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                        {{ m.nickname || m.id }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Event log -->
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold mb-3 text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                Event Log ({{ logEntries.length }})
            </h2>
            <div v-if="logEntries.length === 0" class="text-sm text-slate-400 dark:text-slate-500 italic">
                No events yet. Connect and join a game channel to see events.
            </div>
            <div v-else class="space-y-1 max-h-96 overflow-y-auto font-mono text-xs">
                <div
                    v-for="(entry, i) in logEntries"
                    :key="i"
                    class="flex gap-2 p-2 rounded"
                    :class="{
                        'bg-emerald-50 dark:bg-emerald-950/30': entry.type === 'success',
                        'bg-red-50 dark:bg-red-950/30': entry.type === 'error',
                        'bg-blue-50 dark:bg-blue-950/30': entry.type === 'event',
                        'bg-slate-100 dark:bg-slate-800': entry.type === 'info',
                    }"
                >
                    <span class="text-slate-400 shrink-0">{{ entry.time }}</span>
                    <span :class="{
                        'text-emerald-700 dark:text-emerald-300': entry.type === 'success',
                        'text-red-700 dark:text-red-300': entry.type === 'error',
                        'text-blue-700 dark:text-blue-300': entry.type === 'event',
                        'text-slate-700 dark:text-slate-300': entry.type === 'info',
                    }">{{ entry.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { getConnectionState, joinGame, leaveGame, disconnect } from '../services/websocket.js';

const logEntries = ref([]);
const connectionState = ref('disconnected');
const channelCode = ref('');
const subscribedChannel = ref(null);
const presenceMembers = ref([]);
let statePoller = null;
let activeChannel = null;

const config = computed(() => {
    const reverb = usePage().props?.reverb;
    return {
        host: window.location.hostname,
        port: reverb?.port || document.querySelector('meta[name="reverb-port"]')?.content || '?',
        key: reverb?.key || document.querySelector('meta[name="reverb-key"]')?.content || '',
        tls: window.location.protocol === 'https:',
        hasAuth: !!localStorage.getItem('select-auth-token'),
        hasGuest: !!localStorage.getItem('select-guest-token'),
    };
});

const statusLabel = computed(() => {
    switch (connectionState.value) {
        case 'connected': return 'Connected';
        case 'connecting': return 'Connecting...';
        case 'unavailable': return 'Unavailable';
        case 'failed': return 'Failed';
        case 'disconnected': return 'Disconnected';
        default: return connectionState.value;
    }
});

const statusDetail = computed(() => {
    switch (connectionState.value) {
        case 'connected': return 'WebSocket connection is active';
        case 'connecting': return 'Attempting to connect to Reverb server...';
        case 'unavailable': return 'Server unreachable. Check Apache proxy and Reverb container.';
        case 'failed': return 'Connection failed. Check browser console for details.';
        case 'disconnected': return 'Click Connect to test the WebSocket connection';
        default: return '';
    }
});

const statusClasses = computed(() => {
    switch (connectionState.value) {
        case 'connected': return 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-200';
        case 'connecting': return 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200';
        case 'unavailable':
        case 'failed': return 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-800 dark:text-red-200';
        default: return 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
    }
});

const dotClasses = computed(() => {
    switch (connectionState.value) {
        case 'connected': return 'bg-emerald-500 animate-pulse';
        case 'connecting': return 'bg-amber-500 animate-pulse';
        case 'unavailable':
        case 'failed': return 'bg-red-500';
        default: return 'bg-slate-400';
    }
});

function log(message, type = 'info') {
    const now = new Date();
    const time = now.toLocaleTimeString('en-GB', { hour12: false });
    logEntries.value.unshift({ time, message, type });
    if (logEntries.value.length > 200) {
        logEntries.value.pop();
    }
}

function connect() {
    log('Connecting to Reverb...', 'info');
    try {
        // Trigger Echo initialization by joining a dummy operation
        const state = getConnectionState();
        log(`Initial state: ${state}`, 'info');

        // Start polling connection state
        startStatePoller();

        // Force Echo init by accessing it
        joinGame('__test__');
        leaveGame('__test__');

        setTimeout(() => {
            const newState = getConnectionState();
            connectionState.value = newState;
            if (newState === 'connected') {
                log('Connected successfully!', 'success');
            } else {
                log(`State after init: ${newState}`, 'info');
            }
        }, 2000);
    } catch (e) {
        log(`Connection error: ${e.message}`, 'error');
    }
}

function handleDisconnect() {
    leaveCurrentChannel();
    disconnect();
    connectionState.value = 'disconnected';
    stopStatePoller();
    log('Disconnected', 'info');
}

function joinChannel() {
    const code = channelCode.value.trim().toUpperCase();
    if (!code) return;

    log(`Joining presence-game.${code}...`, 'info');

    try {
        activeChannel = joinGame(code);
        subscribedChannel.value = code;

        activeChannel
            .here((members) => {
                presenceMembers.value = members;
                log(`Channel joined! ${members.length} member(s) present: ${members.map(m => m.nickname || m.id).join(', ')}`, 'success');
            })
            .joining((member) => {
                presenceMembers.value.push(member);
                log(`Member joined: ${member.nickname || member.id}`, 'event');
            })
            .leaving((member) => {
                presenceMembers.value = presenceMembers.value.filter(m => m.id !== member.id);
                log(`Member left: ${member.nickname || member.id}`, 'event');
            })
            .listen('.player.joined', (data) => {
                log(`Event: player.joined — ${JSON.stringify(data)}`, 'event');
            })
            .listen('.player.left', (data) => {
                log(`Event: player.left — ${JSON.stringify(data)}`, 'event');
            })
            .listen('.game.started', (data) => {
                log(`Event: game.started — ${JSON.stringify(data)}`, 'event');
            })
            .listen('.round.started', (data) => {
                log(`Event: round.started — acronym: ${data.acronym}`, 'event');
            })
            .listen('.answer.submitted', (data) => {
                log(`Event: answer.submitted — ${data.answers_count}/${data.total_players}`, 'event');
            })
            .listen('.voting.started', (data) => {
                log(`Event: voting.started — ${data.answers?.length} answers`, 'event');
            })
            .listen('.vote.submitted', (data) => {
                log(`Event: vote.submitted — ${data.votes_count}/${data.total_voters}`, 'event');
            })
            .listen('.round.completed', (data) => {
                log(`Event: round.completed — ${JSON.stringify(data.scores)}`, 'event');
            })
            .listen('.game.finished', (data) => {
                log(`Event: game.finished — winner: ${data.winner?.nickname}`, 'event');
            })
            .listen('.chat.message', (data) => {
                log(`Event: chat.message — ${data.nickname}: ${data.message}`, 'event');
            })
            .error((error) => {
                log(`Channel error: ${JSON.stringify(error)}`, 'error');
            });
    } catch (e) {
        log(`Join error: ${e.message}`, 'error');
    }
}

function leaveCurrentChannel() {
    if (subscribedChannel.value) {
        leaveGame(subscribedChannel.value);
        log(`Left channel presence-game.${subscribedChannel.value}`, 'info');
        subscribedChannel.value = null;
        presenceMembers.value = [];
        activeChannel = null;
    }
}

function leaveChannel() {
    leaveCurrentChannel();
}

function startStatePoller() {
    stopStatePoller();
    statePoller = setInterval(() => {
        const state = getConnectionState();
        if (state !== connectionState.value) {
            const prev = connectionState.value;
            connectionState.value = state;
            log(`Connection state: ${prev} → ${state}`, state === 'connected' ? 'success' : state === 'unavailable' || state === 'failed' ? 'error' : 'info');
        }
    }, 1000);
}

function stopStatePoller() {
    if (statePoller) {
        clearInterval(statePoller);
        statePoller = null;
    }
}

onMounted(() => {
    log('WebSocket test page loaded', 'info');
    log(`Config: ${config.value.tls ? 'wss' : 'ws'}://${config.value.host}:${config.value.port}`, 'info');
});

onUnmounted(() => {
    leaveCurrentChannel();
    stopStatePoller();
});
</script>
