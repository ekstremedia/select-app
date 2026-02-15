<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <!-- Kicked/Banned notification -->
        <div v-if="kickNotice" class="mb-6 p-4 rounded-xl border text-center" :class="kickNotice === 'banned' ? 'bg-red-50 dark:bg-red-950/50 border-red-300 dark:border-red-700' : 'bg-amber-50 dark:bg-amber-950/50 border-amber-300 dark:border-amber-700'">
            <p class="font-medium" :class="kickNotice === 'banned' ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300'">
                {{ kickNotice === 'banned' ? t('lobby.bannedNotification') : t('lobby.kickedNotification') }}
            </p>
            <p v-if="banReason" class="text-sm mt-1" :class="kickNotice === 'banned' ? 'text-red-600 dark:text-red-400' : ''">
                {{ banReason }}
            </p>
        </div>

        <h1 class="text-3xl font-bold mb-8 text-slate-800 dark:text-slate-200">
            {{ t('games.title') }}
        </h1>

        <!-- Game started notification -->
        <div v-if="gameStartedNotice" class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 flex items-center justify-between">
            <div>
                <p class="font-medium text-emerald-700 dark:text-emerald-300">{{ t('games.gameStarted') }}</p>
                <p class="text-sm text-emerald-600 dark:text-emerald-400">
                    #{{ gameStartedNotice.code }} — {{ t('game.round') }} {{ gameStartedNotice.current_round }}/{{ gameStartedNotice.total_rounds }}
                </p>
            </div>
            <Button
                :label="t('games.rejoin')"
                severity="success"
                size="small"
                @click="router.visit(`/spill/${gameStartedNotice.code}`)"
            />
        </div>

        <!-- My active games -->
        <div v-if="myGames.length > 0" class="mb-8">
            <h2 class="text-lg font-semibold mb-3 text-slate-800 dark:text-slate-200">
                {{ t('games.myGames') }}
            </h2>
            <div class="space-y-3">
                <div
                    v-for="game in myGames"
                    :key="game.code"
                    class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 hover:border-emerald-400 dark:hover:border-emerald-600 transition-colors cursor-pointer"
                    @click="router.visit(`/spill/${game.code}`)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">
                                #{{ game.code }}
                            </span>
                            <Badge v-if="game.has_password" :value="t('create.private')" severity="warn" />
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                {{ game.host_nickname }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <Badge
                                v-if="game.status && game.status !== 'lobby'"
                                :value="game.status === 'voting' ? t('games.statusVoting') : `${t('game.round')} ${game.current_round}/${game.total_rounds}`"
                                :severity="game.status === 'voting' ? 'warn' : 'info'"
                            />
                            <Badge
                                v-else
                                :value="t('games.statusLobby')"
                                severity="success"
                            />
                            <Badge :value="`${game.player_count}/${game.max_players ?? 10}`" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open games list -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold mb-3 text-slate-800 dark:text-slate-200">
                {{ t('games.availableGames') }}
            </h2>

            <div v-if="loading" class="space-y-3">
                <Skeleton v-for="i in 3" :key="i" height="4rem" />
            </div>

            <div v-else-if="openGames.length === 0" class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                <p class="text-slate-500 dark:text-slate-400">{{ t('games.noGames') }}</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="game in openGames"
                    :key="game.code"
                    class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors cursor-pointer"
                    @click="handleJoin(game.code)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">
                                #{{ game.code }}
                            </span>
                            <Badge v-if="game.has_password" :value="t('create.private')" severity="warn" />
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                {{ game.host_nickname }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <Badge
                                v-if="game.status && game.status !== 'lobby'"
                                :value="game.status === 'voting' ? t('games.statusVoting') : `${t('game.round')} ${game.current_round}/${game.total_rounds}`"
                                :severity="game.status === 'voting' ? 'warn' : 'info'"
                            />
                            <Badge
                                v-else
                                :value="t('games.statusLobby')"
                                severity="success"
                            />
                            <Badge :value="`${game.player_count}/${game.max_players ?? 10}`" />
                            <span class="text-xs text-slate-400">
                                {{ game.rounds ?? 8 }} {{ t('games.rounds') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions panel -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Button
                :label="t('games.create')"
                severity="success"
                size="large"
                class="w-full"
                @click="router.visit('/spill/opprett')"
            />

            <!-- Join by code -->
            <form @submit.prevent="handleJoinByCode" class="flex gap-2 items-start">
                <InputText
                    v-model="joinCode"
                    :placeholder="t('games.code')"
                    maxlength="6"
                    class="flex-1 min-w-0 text-center uppercase tracking-[0.2em] font-mono"
                    @input="joinCode = joinCode.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                />
                <Button
                    type="submit"
                    :label="t('games.join')"
                    severity="success"
                    :disabled="joinCode.length < 4"
                />
            </form>
            <small v-if="joinError" class="text-red-500 col-span-full">{{ joinError }}</small>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Badge from 'primevue/badge';
import { api, getApiError } from '../services/api.js';
import { useGameStore } from '../stores/gameStore.js';
import { useI18n } from '../composables/useI18n.js';

const gameStore = useGameStore();
const { t } = useI18n();

const openGames = ref([]);
const myGames = ref([]);
const loading = ref(true);
const joinCode = ref('');
const joinError = ref('');
const kickNotice = ref(null);
const banReason = ref(null);
const gameStartedNotice = ref(null);

const apiErrorMap = {
    'Game not found': 'common.gameNotFound',
    'Player not found': 'common.playerNotFound',
};

function translateApiError(msg) {
    if (!msg) return null;
    const key = apiErrorMap[msg];
    return key ? t(key) : msg;
}

async function loadGames() {
    try {
        const { data } = await api.games.list();
        openGames.value = data.games ?? data.data ?? [];
        myGames.value = data.my_games ?? [];

        // Check if any of my games just started
        const started = myGames.value.find(g => g.status !== 'lobby');
        if (started && !gameStartedNotice.value) {
            gameStartedNotice.value = started;
        }
    } catch {
        // Keep existing list on error during polling
    } finally {
        loading.value = false;
    }
}

async function handleJoin(code) {
    joinError.value = '';
    try {
        await gameStore.joinGame(code);
    } catch (err) {
        // "Player already in game" is fine — just navigate to the game
        const msg = err.response?.data?.error || err.response?.data?.message || '';
        if (!msg.toLowerCase().includes('already in game')) {
            joinError.value = translateApiError(msg) || getApiError(err, t);
            return;
        }
    }
    router.visit(`/spill/${code}`);
}

async function handleJoinByCode() {
    if (joinCode.value.length < 4) return;
    await handleJoin(joinCode.value);
}

let pollInterval = null;

onMounted(() => {
    // Check for kicked/banned notification
    const kicked = sessionStorage.getItem('select-kicked');
    if (kicked) {
        kickNotice.value = kicked; // 'kicked' or 'banned'
        banReason.value = sessionStorage.getItem('select-banned-reason');
        sessionStorage.removeItem('select-kicked');
        sessionStorage.removeItem('select-banned-reason');
    }

    loadGames();
    pollInterval = setInterval(loadGames, 10000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>
