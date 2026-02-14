<template>
    <GameLayout
        :game-code="gameStore.gameCode || route.params.code"
        :player-count="gameStore.players.length"
        @leave="handleLeave"
    >
        <div class="flex flex-col h-full overflow-hidden">
            <!-- Loading state -->
            <div v-if="loading" class="flex-1 flex items-center justify-center">
                <ProgressBar mode="indeterminate" class="w-48" />
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center px-4">
                <p class="text-lg text-slate-500 dark:text-slate-400 mb-4">{{ error }}</p>
                <Button :label="t('common.retry')" severity="secondary" @click="initGame" />
            </div>

            <!-- Game content -->
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
                    <ProgressBar
                        :value="timerPercent"
                        :showValue="false"
                        :class="{ 'timer-warning': gameStore.timeRemaining <= 10 }"
                        style="height: 4px"
                    />
                </div>

                <!-- Phase: Lobby -->
                <div v-if="phase === 'lobby'" class="flex-1 overflow-y-auto">
                    <div class="max-w-md mx-auto px-4 py-8 text-center">
                        <h2 class="text-2xl font-bold mb-2 text-slate-800 dark:text-slate-200">
                            {{ t('lobby.title') }}
                        </h2>

                        <!-- Game code display -->
                        <div class="my-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mb-1">{{ t('lobby.gameCode') }}</p>
                            <p class="text-3xl font-mono font-bold tracking-[0.4em] text-emerald-700 dark:text-emerald-300">
                                {{ gameStore.gameCode }}
                            </p>
                            <Button
                                :label="copied ? t('lobby.copied') : t('lobby.copyCode')"
                                size="small"
                                severity="success"
                                variant="text"
                                class="mt-2"
                                @click="copyCode"
                            />
                        </div>

                        <!-- Player list -->
                        <div class="space-y-2 mb-6">
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

                        <!-- Settings summary -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left mb-6">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">{{ t('lobby.settings') }}</h3>
                            <div class="grid grid-cols-2 gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <span>{{ t('create.rounds') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.rounds ?? 5 }}</span>
                                <span>{{ t('create.answerTime') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.answer_time ?? 60 }}s</span>
                                <span>{{ t('create.voteTime') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.vote_time ?? 30 }}s</span>
                            </div>
                        </div>

                        <!-- Start button (host only) -->
                        <div v-if="gameStore.isHost">
                            <Button
                                :label="t('lobby.startGame')"
                                severity="success"
                                size="large"
                                class="w-full"
                                :disabled="gameStore.players.length < 2"
                                :loading="startLoading"
                                @click="handleStart"
                            />
                            <p v-if="gameStore.players.length < 2" class="text-sm text-slate-400 mt-2">
                                {{ t('lobby.needMorePlayers') }}
                            </p>
                        </div>
                        <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t('lobby.waitingForHost') }}
                        </p>
                    </div>
                </div>

                <!-- Phase: Playing (answer input) -->
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

                        <!-- Already submitted -->
                        <div v-if="gameStore.hasSubmittedAnswer" class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                            <p class="text-emerald-700 dark:text-emerald-300 font-medium mb-2">{{ gameStore.myAnswer?.text }}</p>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400">{{ t('game.waitingForOthers') }}</p>
                            <p v-if="gameStore.currentRound" class="text-xs text-slate-400 mt-2">
                                {{ gameStore.currentRound.answers_count ?? 0 }}/{{ gameStore.currentRound.total_players ?? gameStore.players.length }} {{ t('game.submitted') }}
                            </p>
                        </div>

                        <!-- Answer input -->
                        <form v-else @submit.prevent="handleSubmitAnswer" class="space-y-4">
                            <div class="relative">
                                <textarea
                                    ref="answerInput"
                                    v-model="answerText"
                                    :placeholder="t('game.yourAnswer')"
                                    rows="3"
                                    class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                                    @input="validateAnswer"
                                ></textarea>
                            </div>

                            <!-- Letter validation feedback -->
                            <div class="flex justify-center gap-1">
                                <span
                                    v-for="(match, i) in letterMatches"
                                    :key="i"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded text-sm font-bold"
                                    :class="match.status === 'correct' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : match.status === 'wrong' ? 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-400'"
                                >
                                    {{ match.expected }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-400">
                                {{ validWordCount }}/{{ acronymLetters.length }} {{ t('game.wordsMatch') }}
                            </p>

                            <Button
                                type="submit"
                                :label="t('game.submitAnswer')"
                                severity="success"
                                class="w-full"
                                :disabled="!isAnswerValid"
                                :loading="submitLoading"
                            />
                        </form>
                    </div>
                </div>

                <!-- Phase: Voting -->
                <div v-else-if="phase === 'voting'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.voting') }}
                        </h2>

                        <!-- Acronym reminder -->
                        <div class="flex justify-center gap-2 mb-6">
                            <span
                                v-for="(letter, i) in acronymLetters"
                                :key="i"
                                class="inline-flex items-center justify-center w-8 h-8 rounded text-sm font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"
                            >
                                {{ letter }}
                            </span>
                        </div>

                        <!-- Already voted -->
                        <div v-if="gameStore.hasVoted" class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-center">
                            <p class="text-emerald-700 dark:text-emerald-300">{{ t('game.waitingForOthers') }}</p>
                            <p v-if="gameStore.currentRound" class="text-xs text-slate-400 mt-2">
                                {{ gameStore.currentRound.votes_count ?? 0 }}/{{ gameStore.currentRound.total_voters ?? 0 }} {{ t('game.votes') }}
                            </p>
                        </div>

                        <!-- Vote cards -->
                        <div v-else class="space-y-3">
                            <div
                                v-for="answer in gameStore.answers"
                                :key="answer.id"
                                class="p-4 rounded-xl border-2 cursor-pointer transition-all"
                                :class="selectedVote === answer.id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/50' : answer.is_own ? 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 opacity-50 cursor-not-allowed' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 hover:border-emerald-300 dark:hover:border-emerald-700'"
                                @click="!answer.is_own && selectVote(answer.id)"
                            >
                                <p class="text-slate-800 dark:text-slate-200">{{ answer.text }}</p>
                                <p v-if="answer.is_own" class="text-xs text-slate-400 mt-1">{{ t('game.yourSubmission') }}</p>
                            </div>

                            <Button
                                v-if="selectedVote"
                                :label="t('game.confirmVote')"
                                severity="success"
                                class="w-full mt-4"
                                :loading="voteLoading"
                                @click="handleVote"
                            />
                        </div>
                    </div>
                </div>

                <!-- Phase: Results -->
                <div v-else-if="phase === 'results'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.results') }}
                        </h2>

                        <!-- Round results -->
                        <div class="space-y-3 mb-8">
                            <div
                                v-for="(result, i) in gameStore.roundResults"
                                :key="result.answer_id || i"
                                class="p-4 rounded-xl border border-slate-200 dark:border-slate-800"
                                :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-900' : 'bg-slate-50 dark:bg-slate-900'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-slate-800 dark:text-slate-200">{{ result.text }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ result.player_nickname }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <Badge :value="result.votes_count" severity="success" />
                                        <p class="text-xs text-slate-400 mt-1">{{ result.votes_count }} {{ t('game.votes') }}</p>
                                    </div>
                                </div>
                                <Badge v-if="i === 0" :value="t('game.winner')" severity="success" class="mt-2" />
                            </div>
                        </div>

                        <!-- Scoreboard -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.scoreboard') }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="score in gameStore.scores"
                                    :key="score.player_id"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ score.nickname }}</span>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }} {{ t('game.points') }}</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-center text-sm text-slate-400 mt-4">
                            {{ t('game.nextRound') }}...
                        </p>
                    </div>
                </div>

                <!-- Phase: Finished -->
                <div v-else-if="phase === 'finished'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-8 text-center">
                        <h2 class="text-3xl font-bold mb-2 text-emerald-600 dark:text-emerald-400">
                            {{ t('game.finished') }}
                        </h2>

                        <!-- Winner -->
                        <div v-if="gameStore.currentGame?.winner" class="my-6 p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mb-1">{{ t('game.winner') }}</p>
                            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                {{ gameStore.currentGame.winner.nickname }}
                            </p>
                        </div>

                        <!-- Final scores -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.finalScores') }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="(score, i) in gameStore.scores"
                                    :key="score.player_id"
                                    class="flex items-center justify-between p-2 rounded"
                                    :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50' : ''"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-400 w-5">{{ i + 1 }}.</span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ score.nickname }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <Button
                                :label="t('game.playAgain')"
                                severity="success"
                                class="flex-1"
                                @click="router.push('/games')"
                            />
                            <Button
                                :label="t('game.viewArchive')"
                                severity="secondary"
                                variant="outlined"
                                class="flex-1"
                                @click="router.push(`/archive/${route.params.code}`)"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Chat panel toggle -->
            <div class="shrink-0 border-t border-slate-200 dark:border-slate-800">
                <button
                    @click="chatOpen = !chatOpen"
                    class="w-full px-4 py-2 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors"
                >
                    <span>{{ t('game.chat') }}</span>
                    <Badge v-if="unreadCount > 0" :value="unreadCount" severity="danger" />
                    <svg :class="{ 'rotate-180': chatOpen }" class="w-4 h-4 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                </button>

                <!-- Chat messages -->
                <div v-if="chatOpen" class="border-t border-slate-200 dark:border-slate-800 max-h-48 flex flex-col">
                    <div ref="chatContainer" class="flex-1 overflow-y-auto p-3 space-y-1 min-h-0">
                        <div
                            v-for="(msg, i) in gameStore.chatMessages"
                            :key="i"
                            class="text-sm"
                            :class="msg.system ? 'text-slate-400 dark:text-slate-500 italic' : ''"
                        >
                            <span v-if="!msg.system" class="font-medium text-emerald-600 dark:text-emerald-400">{{ msg.nickname }}: </span>
                            <span class="text-slate-700 dark:text-slate-300">{{ msg.message }}</span>
                        </div>
                        <p v-if="gameStore.chatMessages.length === 0" class="text-xs text-slate-400 text-center py-2">
                            ...
                        </p>
                    </div>
                    <form @submit.prevent="handleSendChat" class="flex gap-2 p-2 border-t border-slate-100 dark:border-slate-800">
                        <InputText
                            v-model="chatMessage"
                            :placeholder="t('game.sendMessage')"
                            class="flex-1"
                            size="small"
                        />
                        <Button type="submit" icon="pi pi-send" severity="success" size="small" :disabled="!chatMessage.trim()" />
                    </form>
                </div>
            </div>
        </div>
    </GameLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import ProgressBar from 'primevue/progressbar';
import GameLayout from '../layouts/GameLayout.vue';
import { useGameStore } from '../stores/gameStore.js';
import { useI18n } from '../composables/useI18n.js';

const router = useRouter();
const route = useRoute();
const gameStore = useGameStore();
const { t } = useI18n();

const { phase } = storeToRefs(gameStore);

const loading = ref(true);
const error = ref('');
const startLoading = ref(false);
const submitLoading = ref(false);
const voteLoading = ref(false);
const answerText = ref('');
const selectedVote = ref(null);
const chatOpen = ref(false);
const chatMessage = ref('');
const chatContainer = ref(null);
const answerInput = ref(null);
const copied = ref(false);
const unreadCount = ref(0);

const totalRounds = computed(() => gameStore.currentGame?.settings?.rounds ?? 5);

const acronymLetters = computed(() => {
    return gameStore.acronym ? gameStore.acronym.split('') : [];
});

const showTimer = computed(() => {
    return phase.value === 'playing' || phase.value === 'voting';
});

const timerPercent = computed(() => {
    if (!gameStore.deadline) return 100;
    const total = phase.value === 'voting'
        ? (gameStore.currentGame?.settings?.vote_time ?? 30)
        : (gameStore.currentGame?.settings?.answer_time ?? 60);
    return Math.max(0, (gameStore.timeRemaining / total) * 100);
});

const letterMatches = computed(() => {
    const words = answerText.value.trim().split(/\s+/).filter(Boolean);
    return acronymLetters.value.map((letter, i) => {
        if (i >= words.length) {
            return { expected: letter, status: 'empty' };
        }
        const firstChar = words[i].charAt(0).toUpperCase();
        return {
            expected: letter,
            status: firstChar === letter.toUpperCase() ? 'correct' : 'wrong',
        };
    });
});

const validWordCount = computed(() => {
    return letterMatches.value.filter((m) => m.status === 'correct').length;
});

const isAnswerValid = computed(() => {
    return letterMatches.value.length > 0 && letterMatches.value.every((m) => m.status === 'correct');
});

function validateAnswer() {
    // Real-time validation is handled by letterMatches computed
}

async function initGame() {
    loading.value = true;
    error.value = '';

    try {
        await gameStore.fetchGame(route.params.code);
        gameStore.connectWebSocket(route.params.code);

        // If the game is already playing, fetch the current round
        if (phase.value === 'playing' || phase.value === 'voting') {
            await gameStore.fetchCurrentRound(route.params.code);
        }
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}

async function handleLeave() {
    try {
        await gameStore.leaveGame(route.params.code);
        router.push('/games');
    } catch {
        router.push('/games');
    }
}

async function handleStart() {
    startLoading.value = true;
    try {
        await gameStore.startGame(route.params.code);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        startLoading.value = false;
    }
}

async function handleSubmitAnswer() {
    if (!isAnswerValid.value || !gameStore.currentRound) return;

    submitLoading.value = true;
    try {
        await gameStore.submitAnswer(gameStore.currentRound.id, answerText.value.trim());
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        submitLoading.value = false;
    }
}

function selectVote(answerId) {
    selectedVote.value = selectedVote.value === answerId ? null : answerId;
}

async function handleVote() {
    if (!selectedVote.value || !gameStore.currentRound) return;

    voteLoading.value = true;
    try {
        await gameStore.submitVote(gameStore.currentRound.id, selectedVote.value);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        voteLoading.value = false;
    }
}

async function handleSendChat() {
    if (!chatMessage.value.trim()) return;
    try {
        await gameStore.sendChatMessage(route.params.code, chatMessage.value.trim());
        chatMessage.value = '';
    } catch {
        // Ignore chat errors
    }
}

function copyCode() {
    navigator.clipboard.writeText(gameStore.gameCode || route.params.code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// Track unread chat messages when chat is closed
watch(() => gameStore.chatMessages.length, () => {
    if (!chatOpen.value) {
        unreadCount.value++;
    } else {
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }
});

watch(chatOpen, (open) => {
    if (open) {
        unreadCount.value = 0;
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }
});

// Reset answer text when a new round starts
watch(phase, (newPhase) => {
    if (newPhase === 'playing') {
        answerText.value = '';
        selectedVote.value = null;
    }
    if (newPhase === 'voting') {
        selectedVote.value = null;
    }
});

onMounted(initGame);

onUnmounted(() => {
    gameStore.disconnectWebSocket();
});
</script>
