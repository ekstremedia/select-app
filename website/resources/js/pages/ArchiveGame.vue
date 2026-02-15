<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <!-- Back link -->
        <Link href="/arkiv" class="inline-flex items-center gap-1 text-sm text-emerald-600 dark:text-emerald-400 hover:underline mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            {{ t('archive.backToArchive') }}
        </Link>

        <!-- Loading -->
        <div v-if="loading" class="space-y-6">
            <Skeleton width="200px" height="2rem" />
            <Skeleton height="8rem" />
            <Skeleton height="12rem" />
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-center py-16">
            <p class="text-lg text-slate-500 dark:text-slate-400 mb-4">{{ error }}</p>
            <Button :label="t('common.retry')" severity="secondary" @click="loadGame" />
        </div>

        <!-- Game detail -->
        <template v-else-if="game">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-slate-200">
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">#{{ game.code }}</span>
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ formatDate(game.finished_at) }}
                    </p>
                </div>
                <Button
                    :label="t('archive.shareGame')"
                    severity="secondary"
                    variant="outlined"
                    size="small"
                    @click="shareGame"
                />
            </div>

            <!-- Final standings -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-8">
                <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                    {{ t('archive.finalStandings') }}
                </h2>
                <div class="space-y-3">
                    <div
                        v-for="(player, i) in game.standings"
                        :key="player.player_id"
                        class="flex items-center justify-between p-3 rounded-xl"
                        :class="player.is_winner ? 'bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900' : 'border border-slate-200 dark:border-slate-800'"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold w-8 text-center" :class="player.is_winner ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                                {{ i + 1 }}
                            </span>
                            <Link
                                :href="`/profil/${player.nickname}`"
                                class="font-medium text-slate-800 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400"
                            >
                                {{ player.nickname }}
                            </Link>
                        </div>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ player.score }} {{ t('game.points') }}</span>
                    </div>
                </div>
            </div>

            <!-- Rounds -->
            <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                {{ t('games.rounds') }}
            </h2>
            <div class="space-y-3">
                <div
                    v-for="round in game.rounds"
                    :key="round.round_number"
                    class="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden"
                >
                    <!-- Round header (clickable) -->
                    <button
                        @click="toggleRound(round.round_number)"
                        class="w-full flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-slate-400">{{ t('game.round') }} {{ round.round_number }}</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">{{ round.acronym }}</span>
                        </div>
                        <svg
                            :class="{ 'rotate-180': expandedRounds.includes(round.round_number) }"
                            class="w-5 h-5 text-slate-400 transition-transform"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        ><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    <!-- Round answers (expandable) -->
                    <div v-if="expandedRounds.includes(round.round_number)" class="border-t border-slate-200 dark:border-slate-800">
                        <div
                            v-for="(answer, j) in round.answers"
                            :key="answer.id"
                            class="p-4 border-b last:border-b-0 border-slate-100 dark:border-slate-800"
                            :class="j === 0 ? 'bg-emerald-50/50 dark:bg-emerald-950/30' : ''"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-slate-800 dark:text-slate-200">{{ answer.text?.toLowerCase() }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Link
                                            :href="`/profil/${answer.player_nickname}`"
                                            class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
                                        >
                                            {{ answer.player_nickname }}
                                        </Link>
                                        <span v-if="answer.voted_by?.length" class="text-xs text-slate-400">
                                            {{ t('archive.votedBy') }}: {{ answer.voted_by.join(', ') }}
                                        </span>
                                    </div>
                                </div>
                                <Badge :value="answer.votes_count" severity="success" />
                            </div>
                        </div>
                        <div v-if="!round.answers?.length" class="p-4 text-sm text-slate-400 text-center">
                            {{ t('common.loading') }}
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Skeleton from 'primevue/skeleton';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const props = defineProps({ code: String });
const { t } = useI18n();

const game = ref(null);
const loading = ref(true);
const error = ref('');
const expandedRounds = ref([]);

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString();
}

async function loadGame() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.archive.get(props.code);
        game.value = {
            ...(data.game || {}),
            standings: data.players || [],
            rounds: (data.rounds || []).map(r => ({
                ...r,
                answers: r.answers ? r.answers.map(a => ({
                    ...a,
                    player_nickname: a.player_name,
                    voted_by: a.voters || [],
                })) : undefined,
            })),
        };
        // Auto-expand all rounds
        if (game.value.rounds?.length) {
            expandedRounds.value = game.value.rounds.map(r => r.round_number);
        }
    } catch (err) {
        if (err.response?.status === 404) {
            error.value = t('common.notFound');
        } else {
            error.value = err.response?.data?.message || t('common.error');
        }
    } finally {
        loading.value = false;
    }
}

async function toggleRound(roundNumber) {
    const idx = expandedRounds.value.indexOf(roundNumber);
    if (idx >= 0) {
        expandedRounds.value.splice(idx, 1);
        return;
    }

    expandedRounds.value.push(roundNumber);

    // Fetch round details if answers not loaded yet
    const round = game.value?.rounds?.find((r) => r.round_number === roundNumber);
    if (round && !round.answers) {
        try {
            const { data } = await api.archive.round(props.code, roundNumber);
            round.answers = data.answers ?? data.data ?? [];
        } catch {
            round.answers = [];
        }
    }
}

async function shareGame() {
    const url = `${window.location.origin}/arkiv/${props.code}`;
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // Clipboard API not available — ignore silently
    }
}

onMounted(loadGame);
</script>
