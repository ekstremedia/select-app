<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <!-- Loading state -->
        <div v-if="loading" class="space-y-6">
            <Skeleton width="200px" height="2rem" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <Skeleton v-for="i in 6" :key="i" height="5rem" />
            </div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="text-center py-16">
            <p class="text-lg text-slate-500 dark:text-slate-400 mb-4">{{ error }}</p>
            <Button :label="t('common.retry')" severity="secondary" @click="loadProfile" />
        </div>

        <!-- Profile content -->
        <template v-else-if="profile">
            <!-- Player header -->
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ profile.nickname?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-slate-200">
                        {{ profile.nickname }}
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span v-if="profile.is_bot" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">
                            {{ t('profile.botPlayer') }}
                        </span>
                        <span v-else-if="profile.is_guest" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                            {{ t('profile.guestPlayer') }}
                        </span>
                        <p v-if="profile.member_since" class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t('profile.memberSince') }} {{ formatDate(profile.member_since) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-10">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.games_played ?? 0 }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ t('profile.gamesPlayed') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.games_won ?? 0 }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ t('profile.gamesWon') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.win_rate ?? '0%' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ t('profile.winRate') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.rounds_won ?? 0 }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ t('profile.roundsWon') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.votes_received ?? 0 }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ t('profile.votesReceived') }}</p>
                </div>
            </div>

            <!-- Tabs: Best sentences / Game history -->
            <TabView>
                <TabPanel :header="t('profile.bestSentences')">
                    <div v-if="sentences.length === 0" class="py-8 text-center text-slate-500 dark:text-slate-400">
                        {{ t('hallOfFame.noSentences') }}
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="sentence in sentences"
                            :key="sentence.id"
                            class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">
                                        {{ sentence.acronym }}
                                    </span>
                                    <p class="text-slate-800 dark:text-slate-200 mt-1">{{ sentence.text?.toLowerCase() }}</p>
                                </div>
                                <Badge :value="`${sentence.votes_count} ${t('game.votes')}`" severity="success" />
                            </div>
                        </div>
                    </div>
                </TabPanel>
                <TabPanel :header="t('profile.gameHistory')">
                    <div v-if="games.length === 0" class="py-8 text-center text-slate-500 dark:text-slate-400">
                        {{ t('archive.noGames') }}
                    </div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="game in games"
                            :key="game.code"
                            :href="`/arkiv/${game.code}`"
                            class="block p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-mono font-bold text-slate-500 dark:text-slate-400">#{{ game.code }}</span>
                                    <span class="ml-2 text-sm text-slate-600 dark:text-slate-400">{{ formatDate(game.finished_at) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ game.score }} {{ t('game.points') }}</span>
                                    <span class="ml-2 text-xs text-slate-400">{{ game.placement }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </TabPanel>
            </TabView>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Skeleton from 'primevue/skeleton';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Badge from 'primevue/badge';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const props = defineProps({ nickname: String });
const { t } = useI18n();

const profile = ref(null);
const stats = ref(null);
const sentences = ref([]);
const games = ref([]);
const loading = ref(true);
const error = ref('');

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString();
}

async function loadProfile() {
    loading.value = true;
    error.value = '';

    try {
        // Load profile + stats in parallel with sentences and games
        const [profileRes, sentencesRes, gamesRes] = await Promise.all([
            api.players.profile(props.nickname),
            api.players.sentences(props.nickname, { limit: 10 }),
            api.players.games(props.nickname, { limit: 20 }),
        ]);

        profile.value = profileRes.data.player ?? profileRes.data;
        stats.value = profileRes.data.stats ?? null;
        sentences.value = sentencesRes.data.sentences ?? sentencesRes.data.data ?? [];
        games.value = gamesRes.data.games ?? gamesRes.data.data ?? [];
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

onMounted(loadProfile);
</script>
