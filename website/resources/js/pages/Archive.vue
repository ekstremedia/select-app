<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-6 text-slate-800 dark:text-slate-200">
            {{ t('archive.title') }}
        </h1>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <!-- Period tabs -->
            <div class="flex gap-2">
                <Button
                    v-for="period in periods"
                    :key="period.value"
                    :label="period.label"
                    :severity="activePeriod === period.value ? 'success' : 'secondary'"
                    :variant="activePeriod === period.value ? undefined : 'outlined'"
                    size="small"
                    @click="changePeriod(period.value)"
                />
            </div>

            <!-- Player search -->
            <InputText
                v-model="playerFilter"
                :placeholder="t('archive.filterByPlayer')"
                class="sm:ml-auto w-full sm:w-60"
                @input="debouncedLoad"
            />
        </div>

        <!-- Loading -->
        <div v-if="loading && games.length === 0" class="space-y-4">
            <Skeleton v-for="i in 4" :key="i" height="6rem" />
        </div>

        <!-- Empty state -->
        <div v-else-if="games.length === 0" class="py-16 text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">{{ t('archive.noGames') }}</p>
        </div>

        <!-- Game cards -->
        <div v-else class="space-y-4">
            <Link
                v-for="game in games"
                :key="game.code"
                :href="`/arkiv/${game.code}`"
                class="block p-5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">#{{ game.code }}</span>
                        <span class="text-sm text-slate-400">{{ formatDate(game.finished_at) }}</span>
                    </div>
                    <Badge :value="`${game.player_count} ${t('games.players')}`" />
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ t('game.winner') }}:</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ game.winner_nickname || t('game.tie') }}</span>
                    </div>
                    <span class="text-sm text-slate-400">
                        {{ game.rounds_count }} {{ t('games.rounds') }}
                    </span>
                </div>
            </Link>
        </div>

        <!-- Load more -->
        <div v-if="hasMore" class="text-center mt-8">
            <Button
                :label="t('archive.loadMore')"
                severity="secondary"
                variant="outlined"
                :loading="loading"
                @click="loadMore"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Badge from 'primevue/badge';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();

const games = ref([]);
const loading = ref(true);
const activePeriod = ref('all');
const playerFilter = ref('');
const page = ref(1);
const hasMore = ref(false);

const periods = computed(() => [
    { value: 'all', label: t('archive.allTime') },
    { value: 'month', label: t('archive.thisMonth') },
    { value: 'week', label: t('archive.thisWeek') },
]);

let debounceTimer = null;

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString();
}

function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        page.value = 1;
        games.value = [];
        loadGames();
    }, 300);
}

function changePeriod(period) {
    activePeriod.value = period;
    page.value = 1;
    games.value = [];
    loadGames();
}

async function loadGames() {
    loading.value = true;

    try {
        const params = {
            page: page.value,
            period: activePeriod.value !== 'all' ? activePeriod.value : undefined,
            player: playerFilter.value || undefined,
        };
        const { data } = await api.archive.list(params);
        const newGames = data.games ?? data.data ?? [];
        games.value = page.value === 1 ? newGames : [...games.value, ...newGames];
        hasMore.value = data.meta?.has_more ?? (data.next_page_url != null);
    } catch {
        // Keep existing games on error
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    page.value++;
    loadGames();
}

onMounted(loadGames);
</script>
