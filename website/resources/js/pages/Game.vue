<template>
    <GameLayout
        :game-code="gameStore.gameCode || props.code"
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
                            <canvas v-if="qrCanvas" ref="qrTarget" class="mx-auto mt-3 rounded-lg" width="128" height="128"></canvas>
                            <div class="flex items-center justify-center gap-2 mt-3">
                                <Button
                                    :label="copiedLink ? t('lobby.copied') : t('lobby.shareLink')"
                                    size="small"
                                    severity="success"
                                    @click="copyLink"
                                />
                                <Button
                                    :label="copied ? t('lobby.copied') : t('lobby.copyCode')"
                                    size="small"
                                    severity="secondary"
                                    variant="outlined"
                                    @click="copyCode"
                                />
                            </div>
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
                                <div class="flex items-center gap-2">
                                    <Badge v-if="player.id === gameStore.currentGame?.host_player_id" :value="t('lobby.host')" severity="success" />
                                    <Badge v-else-if="player.is_co_host" :value="t('lobby.coHost')" severity="info" />
                                    <Button
                                        v-if="gameStore.isActualHost && player.id !== gameStore.currentGame?.host_player_id"
                                        :label="player.is_co_host ? t('lobby.removeCoHost') : t('lobby.makeCoHost')"
                                        size="small"
                                        :severity="player.is_co_host ? 'secondary' : 'info'"
                                        variant="text"
                                        @click="handleToggleCoHost(player.id)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Visibility toggle (host/co-host) -->
                        <div v-if="gameStore.isHost" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ t('create.visibility') }}</span>
                            <Button
                                :label="gameStore.currentGame?.is_public ? t('create.public') : t('create.private')"
                                :severity="gameStore.currentGame?.is_public ? 'success' : 'secondary'"
                                size="small"
                                @click="handleToggleVisibility"
                            />
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

                        <!-- Submitted + can edit -->
                        <div v-if="gameStore.hasSubmittedAnswer && !isEditing" class="space-y-4">
                            <div class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                                <p class="text-emerald-700 dark:text-emerald-300 font-medium mb-2">{{ gameStore.myAnswer?.text }}</p>
                                <p v-if="gameStore.currentRound" class="text-xs text-slate-400 mt-2">
                                    {{ gameStore.currentRound.answers_count ?? 0 }}/{{ gameStore.currentRound.total_players ?? gameStore.players.length }} {{ t('game.submitted') }}
                                </p>
                            </div>
                            <Button
                                v-if="editsRemaining > 0"
                                :label="`${t('game.edit')} (${editsRemaining} ${t('game.remaining')})`"
                                severity="secondary"
                                variant="outlined"
                                size="small"
                                class="w-full"
                                @click="startEditing"
                            />
                        </div>

                        <!-- Answer input (initial or editing) -->
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

                            <div class="flex gap-2">
                                <Button
                                    v-if="isEditing"
                                    :label="t('common.cancel')"
                                    severity="secondary"
                                    variant="outlined"
                                    class="flex-1"
                                    @click="isEditing = false"
                                />
                                <Button
                                    type="submit"
                                    :label="isEditing ? t('game.updateAnswer') : t('game.submitAnswer')"
                                    severity="success"
                                    :class="isEditing ? 'flex-1' : 'w-full'"
                                    :disabled="!isAnswerValid"
                                    :loading="submitLoading"
                                />
                            </div>
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

                        <!-- Vote status -->
                        <p v-if="gameStore.currentRound" class="text-xs text-slate-400 text-center mb-4">
                            {{ gameStore.currentRound.votes_count ?? 0 }}/{{ gameStore.currentRound.total_voters ?? 0 }} {{ t('game.votes') }}
                        </p>

                        <!-- Vote cards — always visible, click to vote or change vote -->
                        <div class="space-y-3">
                            <div
                                v-for="answer in gameStore.answers"
                                :key="answer.id"
                                class="p-4 rounded-xl border-2 transition-all"
                                :class="
                                    answer.is_own
                                        ? 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 opacity-50 cursor-not-allowed'
                                        : gameStore.myVote?.answer_id === answer.id
                                            ? 'border-emerald-500 dark:border-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 cursor-pointer'
                                            : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 hover:border-emerald-300 dark:hover:border-emerald-700 cursor-pointer'
                                "
                                @click="!answer.is_own && !voteLoading && handleDirectVote(answer.id)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-slate-800 dark:text-slate-200">{{ answer.text }}</p>
                                    <span v-if="gameStore.myVote?.answer_id === answer.id" class="shrink-0 text-emerald-500 dark:text-emerald-400 text-xs font-medium">
                                        {{ t('game.yourVote') }}
                                    </span>
                                </div>
                                <p v-if="answer.is_own" class="text-xs text-slate-400 mt-1">{{ t('game.yourSubmission') }}</p>
                            </div>
                        </div>

                        <p v-if="gameStore.hasVoted" class="text-center text-xs text-slate-400 mt-4">
                            {{ t('game.tapToChangeVote') }}
                        </p>
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
                                :key="result.player_id || i"
                                class="p-4 rounded-xl border border-slate-200 dark:border-slate-800"
                                :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-900' : 'bg-slate-50 dark:bg-slate-900'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-slate-800 dark:text-slate-200">{{ result.answer || result.text }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ result.player_name || result.player_nickname }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <Badge :value="result.votes ?? result.votes_count ?? 0" severity="success" />
                                        <p class="text-xs text-slate-400 mt-1">{{ result.votes ?? result.votes_count ?? 0 }} {{ t('game.votes') }}</p>
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
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ score.player_name || score.nickname }}</span>
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
                        <h2 class="text-3xl font-bold mb-2 text-emerald-600 dark:text-emerald-400 animate-bounce-in">
                            {{ t('game.finished') }}
                        </h2>

                        <!-- Winner -->
                        <div v-if="gameStore.currentGame?.winner" class="my-6 p-6 rounded-2xl bg-gradient-to-br from-emerald-50 to-yellow-50 dark:from-emerald-950/50 dark:to-yellow-950/30 border-2 border-emerald-300 dark:border-emerald-700 animate-winner-reveal shadow-lg shadow-emerald-200/50 dark:shadow-emerald-900/50">
                            <div class="text-4xl mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-12 h-12 mx-auto text-yellow-500 animate-trophy-bounce">
                                    <path d="M5 3h14c.55 0 1 .45 1 1v2c0 2.76-2.24 5-5 5h-.42c-.77 1.25-1.94 2.18-3.33 2.65L12 17h2a1 1 0 110 2H10a1 1 0 110-2h2l-.75-3.35C9.84 13.18 8.67 12.25 7.9 11H7.5C5.24 11 3 8.76 3 6V4c0-.55.45-1 1-1h1zm1 2v1c0 1.65 1.35 3 3 3h.09c-.05-.33-.09-.66-.09-1V5H6zm12 0h-3v3c0 .34-.04.67-.09 1H15c1.65 0 3-1.35 3-3V5z"/>
                                </svg>
                            </div>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mb-1 font-medium">{{ t('game.winner') }}</p>
                            <p class="text-3xl font-bold text-emerald-700 dark:text-emerald-300">
                                {{ gameStore.currentGame.winner.player_name || gameStore.currentGame.winner.nickname }}
                            </p>
                            <p class="text-lg font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                                {{ gameStore.currentGame.winner.score }} {{ t('game.points') }}
                            </p>
                        </div>

                        <!-- Final scores -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.finalScores') }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="(score, i) in gameStore.scores"
                                    :key="score.player_id"
                                    class="flex items-center justify-between p-2 rounded transition-all"
                                    :class="i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 ring-1 ring-emerald-200 dark:ring-emerald-800' : ''"
                                    :style="{ animationDelay: `${i * 100}ms` }"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold w-5" :class="i === 0 ? 'text-yellow-500' : i === 1 ? 'text-slate-400' : i === 2 ? 'text-amber-700' : 'text-slate-400'">
                                            {{ i === 0 ? '\uD83E\uDD47' : i === 1 ? '\uD83E\uDD48' : i === 2 ? '\uD83E\uDD49' : `${i + 1}.` }}
                                        </span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ score.player_name || score.nickname }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <Button
                                v-if="gameStore.isHost"
                                :label="t('game.playAgainSamePlayers')"
                                severity="success"
                                class="flex-1"
                                :loading="rematchLoading"
                                @click="handleRematch"
                            />
                            <Button
                                v-else
                                :label="t('game.playAgain')"
                                severity="success"
                                class="flex-1"
                                @click="router.visit('/games')"
                            />
                            <Button
                                :label="t('game.viewArchive')"
                                severity="secondary"
                                variant="outlined"
                                class="flex-1"
                                @click="router.visit(`/archive/${props.code}`)"
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
                    <div class="flex items-center gap-2">
                        <Badge v-if="unreadCount > 0" :value="unreadCount" severity="danger" />
                        <svg :class="{ 'rotate-180': chatOpen }" class="w-4 h-4 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                    </div>
                </button>

                <!-- IRC-style chat -->
                <div v-if="chatOpen" class="border-t border-slate-200 dark:border-slate-800 max-h-48 flex flex-col" @click="focusChatInput">
                    <div ref="chatContainer" class="flex-1 overflow-y-auto px-3 py-1 space-y-0.5 min-h-0 font-mono text-xs">
                        <div
                            v-for="(msg, i) in gameStore.chatMessages"
                            :key="i"
                            :class="msg.system ? 'text-slate-400 dark:text-slate-500' : msg.action ? 'text-purple-500 dark:text-purple-400' : ''"
                        >
                            <template v-if="msg.system">
                                <span class="text-slate-500">*** </span>
                                <span>{{ msg.message }}</span>
                            </template>
                            <template v-else-if="msg.action">
                                <span class="text-slate-500">* </span>
                                <span class="font-medium">{{ msg.nickname }}</span>
                                <span> {{ msg.message }}</span>
                            </template>
                            <template v-else>
                                <span class="text-slate-500">&lt;</span><span class="font-medium text-emerald-600 dark:text-emerald-400">{{ msg.nickname }}</span><span class="text-slate-500">&gt;</span>
                                <span class="text-slate-700 dark:text-slate-300"> {{ msg.message }}</span>
                            </template>
                        </div>
                        <p v-if="gameStore.chatMessages.length === 0" class="text-slate-400 text-center py-2">
                            --- {{ t('game.chat') }} ---
                        </p>
                    </div>
                    <form @submit.prevent="handleSendChat" class="flex gap-1 p-1.5 border-t border-slate-100 dark:border-slate-800" @click.stop>
                        <InputText
                            ref="chatInputRef"
                            v-model="chatMessage"
                            :placeholder="t('game.sendMessage')"
                            class="flex-1 font-mono !text-xs"
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
import { router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import ProgressBar from 'primevue/progressbar';
import QRCode from 'qrcode';
import confetti from 'canvas-confetti';
import GameLayout from '../layouts/GameLayout.vue';
import { useGameStore } from '../stores/gameStore.js';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';

defineOptions({ layout: false });

const props = defineProps({ code: String });

const gameStore = useGameStore();
const authStore = useAuthStore();
const { t } = useI18n();

const { phase } = storeToRefs(gameStore);

const loading = ref(true);
const error = ref('');
const startLoading = ref(false);
const submitLoading = ref(false);
const voteLoading = ref(false);
const answerText = ref('');
const chatOpen = ref(false);
const chatMessage = ref('');
const chatContainer = ref(null);
const answerInput = ref(null);
const chatInputRef = ref(null);
const qrTarget = ref(null);
const qrCanvas = ref(false);
const copied = ref(false);
const copiedLink = ref(false);
const unreadCount = ref(0);
const isEditing = ref(false);
const submitCount = ref(0);
const rematchLoading = ref(false);
const MAX_SUBMISSIONS = 3;
const editsRemaining = computed(() => Math.max(0, MAX_SUBMISSIONS - submitCount.value));

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
        await gameStore.fetchGame(props.code);

        // Auto-join if the player isn't in the game yet (lobby or mid-game)
        const myId = authStore.player?.id;
        const isInGame = gameStore.players.some((p) => p.id === myId);
        if (!isInGame && phase.value !== 'finished') {
            await gameStore.joinGame(props.code);
        }

        gameStore.connectWebSocket(props.code);

        // If the game is active, fetch full state (round, my_answer, answers, my_vote)
        if (phase.value !== 'lobby') {
            await gameStore.fetchGameState(props.code);
        }

        // Generate QR code for lobby
        if (phase.value === 'lobby') {
            generateQR();
        }
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}

async function generateQR() {
    qrCanvas.value = true;
    await nextTick();
    if (qrTarget.value) {
        const url = `${window.location.origin}/games/join?code=${props.code}`;
        QRCode.toCanvas(qrTarget.value, url, { width: 128, margin: 1 });
    }
}

async function handleLeave() {
    try {
        await gameStore.leaveGame(props.code);
        router.visit('/games');
    } catch {
        router.visit('/games');
    }
}

async function handleStart() {
    startLoading.value = true;
    try {
        await gameStore.startGame(props.code);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        startLoading.value = false;
    }
}

function startEditing() {
    answerText.value = gameStore.myAnswer?.text || '';
    isEditing.value = true;
}

async function handleSubmitAnswer() {
    if (!isAnswerValid.value || !gameStore.currentRound) return;

    const trimmed = answerText.value.trim();

    // Don't count as an edit if text is unchanged
    if (isEditing.value && trimmed === gameStore.myAnswer?.text) {
        isEditing.value = false;
        return;
    }

    submitLoading.value = true;
    try {
        await gameStore.submitAnswer(gameStore.currentRound.id, trimmed);
        submitCount.value++;
        isEditing.value = false;
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        submitLoading.value = false;
    }
}

async function handleDirectVote(answerId) {
    if (!answerId || !gameStore.currentRound || voteLoading.value) return;

    voteLoading.value = true;
    try {
        await gameStore.submitVote(gameStore.currentRound.id, answerId);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        voteLoading.value = false;
    }
}

function focusChatInput() {
    nextTick(() => {
        chatInputRef.value?.$el?.focus();
    });
}

async function handleSendChat() {
    const text = chatMessage.value.trim();
    if (!text) return;

    const myNickname = authStore.player?.nickname || 'Anonymous';

    // IRC command handling
    if (text.startsWith('/')) {
        const parts = text.split(' ');
        const cmd = parts[0].toLowerCase();
        const rest = parts.slice(1).join(' ');

        switch (cmd) {
            case '/me':
                if (rest) {
                    // Send as action message
                    try {
                        await gameStore.sendChatMessage(props.code, rest, true);
                        chatMessage.value = '';
                    } catch { /* ignore */ }
                }
                return;
            case '/away':
                gameStore.chatMessages.push({
                    nickname: myNickname,
                    message: rest || 'is away',
                    action: true,
                });
                try {
                    await gameStore.sendChatMessage(props.code, rest || 'is away', true);
                } catch { /* ignore */ }
                chatMessage.value = '';
                return;
            case '/whois': {
                const target = rest.trim();
                const player = gameStore.players.find(p =>
                    p.nickname.toLowerCase() === target.toLowerCase()
                );
                if (player) {
                    const role = player.id === gameStore.currentGame?.host_player_id ? 'Host' : player.is_co_host ? 'Co-host' : 'Player';
                    gameStore.chatMessages.push({
                        system: true,
                        message: `${player.nickname} [${role}] — Score: ${player.score ?? 0}`,
                    });
                } else {
                    gameStore.chatMessages.push({
                        system: true,
                        message: target ? `${target}: No such nick` : 'Usage: /whois <nick>',
                    });
                }
                chatMessage.value = '';
                return;
            }
            case '/quit':
            case '/part':
                chatMessage.value = '';
                handleLeave();
                return;
            default:
                gameStore.chatMessages.push({
                    system: true,
                    message: `Unknown command: ${cmd}`,
                });
                chatMessage.value = '';
                return;
        }
    }

    try {
        await gameStore.sendChatMessage(props.code, text);
        chatMessage.value = '';
    } catch {
        // Ignore chat errors
    }
}

function celebrateWinner() {
    // Initial big burst
    confetti({ particleCount: 150, spread: 100, origin: { y: 0.6 } });
    // Side cannons with delay
    setTimeout(() => {
        confetti({ particleCount: 80, angle: 60, spread: 55, origin: { x: 0, y: 0.7 } });
        confetti({ particleCount: 80, angle: 120, spread: 55, origin: { x: 1, y: 0.7 } });
    }, 300);
    // Gold stars burst
    setTimeout(() => {
        confetti({
            particleCount: 50, spread: 360, startVelocity: 30,
            ticks: 60, origin: { x: 0.5, y: 0.3 },
            colors: ['#FFD700', '#FFA500', '#FF6347'],
            shapes: ['star'],
        });
    }, 700);
    // Final shower
    setTimeout(() => {
        confetti({ particleCount: 100, spread: 160, origin: { y: 0.35 }, gravity: 1.2 });
    }, 1200);
}

async function handleRematch() {
    rematchLoading.value = true;
    try {
        await gameStore.rematch(props.code);
        const newCode = gameStore.gameCode;
        if (newCode) {
            router.visit(`/games/${newCode}`);
        }
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    } finally {
        rematchLoading.value = false;
    }
}

async function handleToggleCoHost(playerId) {
    try {
        await gameStore.toggleCoHost(props.code, playerId);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

async function handleToggleVisibility() {
    try {
        await gameStore.updateVisibility(props.code, !gameStore.currentGame?.is_public);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

function copyCode() {
    navigator.clipboard.writeText(gameStore.gameCode || props.code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function copyLink() {
    const code = gameStore.gameCode || props.code;
    const url = `${window.location.origin}/games/${code}`;
    navigator.clipboard.writeText(url);
    copiedLink.value = true;
    setTimeout(() => { copiedLink.value = false; }, 2000);
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

// Reset state when phase changes
watch(phase, (newPhase) => {
    if (newPhase === 'playing') {
        answerText.value = '';
        isEditing.value = false;
        submitCount.value = 0;
    }
    if (newPhase === 'finished') {
        celebrateWinner();
    }
});

onMounted(async () => {
    if (!authStore.isInitialized) {
        await authStore.loadFromStorage();
    }
    initGame();
});

onUnmounted(() => {
    gameStore.disconnectWebSocket();
});
</script>

<style scoped>
.animate-bounce-in {
    animation: bounceIn 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.animate-winner-reveal {
    animation: winnerReveal 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
}
.animate-trophy-bounce {
    animation: trophyBounce 1s ease-in-out 0.5s both;
}
@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.95); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes winnerReveal {
    0% { transform: scale(0.8) translateY(20px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
@keyframes trophyBounce {
    0% { transform: scale(0) rotate(-20deg); }
    50% { transform: scale(1.3) rotate(10deg); }
    70% { transform: scale(0.9) rotate(-5deg); }
    100% { transform: scale(1) rotate(0deg); }
}
</style>
