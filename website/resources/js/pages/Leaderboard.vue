<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-6 text-slate-800 dark:text-slate-200">
            {{ t('leaderboard.title') }}
        </h1>

        <!-- Period filter -->
        <div class="flex gap-2 mb-6">
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

        <!-- Loading -->
        <div v-if="loading" class="space-y-2">
            <Skeleton height="3rem" v-for="i in 10" :key="i" />
        </div>

        <!-- DataTable -->
        <DataTable
            v-else
            :value="leaderboard"
            stripedRows
            class="rounded-2xl overflow-hidden"
            :pt="{
                root: { class: 'border border-slate-200 dark:border-slate-800 rounded-2xl' }
            }"
        >
            <Column field="rank" :header="t('leaderboard.rank')" style="width: 4rem">
                <template #body="{ data }">
                    <span class="font-bold" :class="data.rank <= 3 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                        {{ data.rank }}
                    </span>
                </template>
            </Column>
            <Column field="nickname" :header="t('leaderboard.player')">
                <template #body="{ data }">
                    <router-link
                        :to="`/profile/${data.nickname}`"
                        class="font-medium text-slate-800 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400"
                    >
                        {{ data.nickname }}
                    </router-link>
                </template>
            </Column>
            <Column field="games_played" :header="t('leaderboard.gamesPlayed')" class="hidden sm:table-cell" />
            <Column field="games_won" :header="t('leaderboard.gamesWon')" />
            <Column field="win_rate" :header="t('leaderboard.winRate')">
                <template #body="{ data }">
                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ data.win_rate }}</span>
                </template>
            </Column>
            <Column field="rounds_won" :header="t('leaderboard.roundsWon')" class="hidden sm:table-cell" />
        </DataTable>

        <!-- Empty state -->
        <div v-if="!loading && leaderboard.length === 0" class="py-16 text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">{{ t('archive.noGames') }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();

const leaderboard = ref([]);
const loading = ref(true);
const activePeriod = ref('all');

const periods = computed(() => [
    { value: 'all', label: t('archive.allTime') },
    { value: 'month', label: t('archive.thisMonth') },
    { value: 'week', label: t('archive.thisWeek') },
]);

function changePeriod(period) {
    activePeriod.value = period;
    loadLeaderboard();
}

async function loadLeaderboard() {
    loading.value = true;

    try {
        const params = {
            period: activePeriod.value !== 'all' ? activePeriod.value : undefined,
        };
        const { data } = await api.leaderboard.get(params);
        const entries = data.leaderboard ?? data.data ?? [];
        leaderboard.value = entries.map((entry, i) => ({
            ...entry,
            rank: entry.rank ?? i + 1,
        }));
    } catch {
        leaderboard.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(loadLeaderboard);
</script>
