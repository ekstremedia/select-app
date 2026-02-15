<template>
    <GameLayout
        :game-code="gameStore.gameCode || props.code"
        :player-count="gameStore.players.length"
        @leave="router.visit('/games')"
    >
        <div class="flex flex-col h-full overflow-hidden">
            <!-- Loading state -->
            <div v-if="loading" class="flex-1 flex items-center justify-center">
                <ProgressBar mode="indeterminate" class="w-48" />
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center px-4">
                <p class="text-lg text-slate-500 dark:text-slate-400 mb-4">{{ error }}</p>
                <Button :label="t('common.retry')" severity="secondary" @click="initSpectate" />
            </div>

            <template v-else>
                <!-- Timer bar -->
                <div v-if="showTimer" class="shrink-0 px-4 py-2 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ t('game.round') }} {{ gameStore.currentRound?.round_number ?? 1 }} {{ t('game.of') }} {{ totalRounds }}
                        </span>
                        <span class="text-sm font-mono font-bold" :class="gameStore.timeRemaining <= 10 ? 'text-red-500' : 'text-slate-700 dark:text-slate-300'">
                            {{ gameStore.timeRemaining }}s
                        </span>
                    </div>
                    <ProgressBar :value="timerPercent" :showValue="false" style="height: 4px" />
                </div>

                <!-- Phase: Lobby -->
                <div v-if="phase === 'lobby'" class="flex-1 overflow-y-auto">
                    <div class="max-w-md mx-auto px-4 py-8 text-center">
                        <h2 class="text-2xl font-bold mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('lobby.title') }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ t('lobby.waitingForHost') }}</p>
                        <div class="space-y-2">
                            <div
                                v-for="player in gameStore.players"
                                :key="player.id"
                                class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ player.nickname?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ player.nickname }}</span>
                                </div>
                                <Badge v-if="player.id === gameStore.currentGame?.host_player_id" :value="t('lobby.host')" severity="success" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phase: Playing -->
                <div v-else-if="phase === 'playing'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6 text-center">
                        <!-- Acronym display -->
                        <div class="flex justify-center gap-2 sm:gap-3 mb-6">
                            <span
                                v-for="(letter, i) in acronymLetters"
                                :key="i"
                                class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-xl text-xl sm:text-3xl font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border-2 border-emerald-300 dark:border-emerald-700"
                            >
                                {{ letter }}
                            </span>
                        </div>

                        <p class="text-slate-500 dark:text-slate-400">
                            {{ gameStore.currentRound?.answers_count ?? 0 }}/{{ gameStore.currentRound?.total_players ?? gameStore.players.length }} {{ t('game.submitted') }}
                        </p>
                    </div>
                </div>

                <!-- Phase: Voting -->
                <div v-else-if="phase === 'voting'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.voting') }}
                        </h2>

                        <div class="flex justify-center gap-2 mb-6">
                            <span
                                v-for="(letter, i) in acronymLetters"
                                :key="i"
                                class="inline-flex items-center justify-center w-8 h-8 rounded text-sm font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"
                            >
                                {{ letter }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="answer in gameStore.answers"
                                :key="answer.id"
                                class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
                            >
                                <p class="text-slate-800 dark:text-slate-200">{{ answer.text?.toLowerCase() }}</p>
                            </div>
                        </div>

                        <p class="text-center text-sm text-slate-400 mt-4">
                            {{ gameStore.currentRound?.votes_count ?? 0 }}/{{ gameStore.currentRound?.total_voters ?? 0 }} {{ t('game.votes') }}
                        </p>
                    </div>
                </div>

                <!-- Phase: Results -->
                <div v-else-if="phase === 'results'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.results') }}
                        </h2>

                        <div class="space-y-3 mb-8">
                            <div
                                v-for="(result, i) in gameStore.roundResults"
                                :key="result.answer_id || i"
                                class="p-4 rounded-xl border border-slate-200 dark:border-slate-800"
                                :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-900' : 'bg-slate-50 dark:bg-slate-900'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-slate-800 dark:text-slate-200">{{ result.text?.toLowerCase() }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ result.player_nickname }}</p>
                                    </div>
                                    <Badge :value="result.votes_count" severity="success" />
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.scoreboard') }}</h3>
                            <div class="space-y-2">
                                <div v-for="score in gameStore.scores" :key="score.player_id" class="flex items-center justify-between">
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ score.nickname }}</span>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phase: Finished -->
                <div v-else-if="phase === 'finished'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-8 text-center">
                        <h2 class="text-3xl font-bold mb-2 text-emerald-600 dark:text-emerald-400">
                            {{ t('game.finished') }}
                        </h2>

                        <div v-if="gameStore.currentGame?.winner" class="my-6 p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mb-1">{{ t('game.winner') }}</p>
                            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ gameStore.currentGame.winner.nickname }}</p>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.finalScores') }}</h3>
                            <div class="space-y-2">
                                <div v-for="(score, i) in gameStore.scores" :key="score.player_id" class="flex items-center justify-between p-2 rounded" :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50' : ''">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-400 w-5">{{ i + 1 }}.</span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ score.nickname }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }}</span>
                                </div>
                            </div>
                        </div>

                        <Button :label="t('game.viewArchive')" severity="secondary" variant="outlined" @click="router.visit(`/archive/${props.code}`)" />
                    </div>
                </div>
            </template>
        </div>
    </GameLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import ProgressBar from 'primevue/progressbar';
import GameLayout from '../layouts/GameLayout.vue';
import { useGameStore } from '../stores/gameStore.js';
import { useI18n } from '../composables/useI18n.js';
import { listenToGame, leaveGame as wsLeaveGame } from '../services/websocket.js';

defineOptions({ layout: false });

const props = defineProps({ code: String });

const gameStore = useGameStore();
const { t } = useI18n();

const { phase } = storeToRefs(gameStore);

const loading = ref(true);
const error = ref('');

const totalRounds = computed(() => gameStore.currentGame?.settings?.rounds ?? 5);
const acronymLetters = computed(() => gameStore.acronym ? gameStore.acronym.split('') : []);
const showTimer = computed(() => phase.value === 'playing' || phase.value === 'voting');

const timerPercent = computed(() => {
    if (!gameStore.deadline) return 100;
    const total = phase.value === 'voting'
        ? (gameStore.currentGame?.settings?.vote_time ?? 30)
        : (gameStore.currentGame?.settings?.answer_time ?? 60);
    return Math.max(0, (gameStore.timeRemaining / total) * 100);
});

async function initSpectate() {
    loading.value = true;
    error.value = '';

    try {
        await gameStore.fetchGame(props.code);

        // Use listen-only channel (not presence join) for spectating
        gameStore.connectWebSocket(props.code);

        if (phase.value === 'playing' || phase.value === 'voting') {
            await gameStore.fetchCurrentRound(props.code);
        }
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}

onMounted(initSpectate);

onUnmounted(() => {
    gameStore.disconnectWebSocket();
});
</script>
