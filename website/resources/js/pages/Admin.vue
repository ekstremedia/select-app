<template>
    <div class="max-w-6xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-6 text-slate-800 dark:text-slate-200">
            {{ t('admin.title') }}
        </h1>

        <TabView>
            <!-- Players tab -->
            <TabPanel :header="t('admin.players')">
                <div class="mb-4">
                    <InputText
                        v-model="playerSearch"
                        :placeholder="t('archive.filterByPlayer')"
                        class="w-full sm:w-80"
                        @input="debouncedLoadPlayers"
                    />
                </div>

                <DataTable
                    :value="adminPlayers"
                    :loading="playersLoading"
                    stripedRows
                    paginator
                    :rows="20"
                    :pt="{ root: { class: 'border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden' } }"
                >
                    <Column field="nickname" header="Nickname">
                        <template #body="{ data }">
                            <Link
                                :href="`/profil/${data.nickname}`"
                                class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                            >
                                {{ data.nickname }}
                            </Link>
                        </template>
                    </Column>
                    <Column field="email" header="Email" class="hidden sm:table-cell" />
                    <Column field="is_guest" header="Type">
                        <template #body="{ data }">
                            <Badge :value="data.is_guest ? 'Guest' : 'User'" :severity="data.is_guest ? 'secondary' : 'success'" />
                        </template>
                    </Column>
                    <Column field="games_played" :header="t('leaderboard.gamesPlayed')" class="hidden sm:table-cell" />
                    <Column field="is_banned" header="Status">
                        <template #body="{ data }">
                            <Badge v-if="data.is_banned" value="Banned" severity="danger" />
                            <Badge v-else value="Active" severity="success" />
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    v-if="data.is_banned"
                                    :label="t('admin.unban')"
                                    severity="success"
                                    size="small"
                                    @click="handleUnban(data)"
                                />
                                <Button
                                    v-else
                                    :label="t('admin.ban')"
                                    severity="danger"
                                    size="small"
                                    variant="outlined"
                                    @click="openBanDialog(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </TabPanel>

            <!-- Games tab -->
            <TabPanel :header="t('admin.games')">
                <DataTable
                    :value="adminGames"
                    :loading="gamesLoading"
                    stripedRows
                    paginator
                    :rows="20"
                    :pt="{ root: { class: 'border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden' } }"
                >
                    <Column field="code" header="Code">
                        <template #body="{ data }">
                            <Link
                                :href="`/arkiv/${data.code}`"
                                class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline tracking-widest"
                            >
                                #{{ data.code }}
                            </Link>
                        </template>
                    </Column>
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Badge :value="data.status" :severity="data.status === 'finished' ? 'success' : data.status === 'playing' ? 'info' : 'secondary'" />
                        </template>
                    </Column>
                    <Column field="player_count" :header="t('games.players')" />
                    <Column field="host_nickname" header="Host" class="hidden sm:table-cell" />
                    <Column field="created_at" header="Created" class="hidden sm:table-cell">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                </DataTable>
            </TabPanel>

            <!-- Stats tab -->
            <TabPanel :header="t('admin.stats')">
                <div v-if="statsLoading" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Skeleton v-for="i in 7" :key="i" height="5rem" />
                </div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div
                        v-for="stat in statsCards"
                        :key="stat.label"
                        class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center"
                    >
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stat.value }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ stat.label }}</p>
                    </div>
                </div>
            </TabPanel>
        </TabView>

        <!-- Ban dialog -->
        <Dialog
            v-model:visible="banDialogVisible"
            :header="`${t('admin.ban')} ${banTarget?.nickname}`"
            modal
            :style="{ width: '25rem' }"
        >
            <div class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('admin.banReason') }}
                    </label>
                    <InputText v-model="banReason" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="banIp" />
                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ t('admin.banIp') }}</span>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button :label="t('common.cancel')" severity="secondary" variant="text" @click="banDialogVisible = false" />
                    <Button :label="t('admin.ban')" severity="danger" :loading="banLoading" @click="handleBan" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();

// Players
const adminPlayers = ref([]);
const playersLoading = ref(true);
const playerSearch = ref('');

// Games
const adminGames = ref([]);
const gamesLoading = ref(true);

// Stats
const statsLoading = ref(true);
const statsData = ref({});

// Ban
const banDialogVisible = ref(false);
const banTarget = ref(null);
const banReason = ref('');
const banIp = ref(false);
const banLoading = ref(false);

let debounceTimer = null;

const statsCards = computed(() => [
    { label: t('admin.players'), value: statsData.value.total_players ?? 0 },
    { label: t('admin.games'), value: statsData.value.total_games ?? 0 },
    { label: t('admin.activeToday'), value: statsData.value.active_today ?? 0 },
    { label: t('admin.gamesToday'), value: statsData.value.games_today ?? 0 },
    { label: t('admin.finished'), value: statsData.value.games_finished ?? 0 },
    { label: t('admin.answers'), value: statsData.value.total_answers ?? 0 },
    { label: t('admin.banned'), value: statsData.value.banned_players ?? 0 },
]);

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString();
}

function debouncedLoadPlayers() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadPlayers, 300);
}

async function loadPlayers() {
    playersLoading.value = true;
    try {
        const { data } = await api.admin.players({ search: playerSearch.value || undefined });
        adminPlayers.value = data.players ?? data.data ?? [];
    } catch {
        adminPlayers.value = [];
    } finally {
        playersLoading.value = false;
    }
}

async function loadGames() {
    gamesLoading.value = true;
    try {
        const { data } = await api.admin.games();
        adminGames.value = data.games ?? data.data ?? [];
    } catch {
        adminGames.value = [];
    } finally {
        gamesLoading.value = false;
    }
}

async function loadStats() {
    statsLoading.value = true;
    try {
        const { data } = await api.admin.stats();
        statsData.value = data;
    } catch {
        statsData.value = {};
    } finally {
        statsLoading.value = false;
    }
}

function openBanDialog(player) {
    banTarget.value = player;
    banReason.value = '';
    banIp.value = false;
    banDialogVisible.value = true;
}

async function handleBan() {
    if (!banTarget.value) return;
    banLoading.value = true;
    try {
        await api.admin.ban(banTarget.value.id, banReason.value, banIp.value);
        banTarget.value.is_banned = true;
        banDialogVisible.value = false;
    } catch {
        // Handle error
    } finally {
        banLoading.value = false;
    }
}

async function handleUnban(player) {
    try {
        await api.admin.unban(player.id);
        player.is_banned = false;
    } catch {
        // Handle error
    }
}

onMounted(() => {
    Promise.all([loadPlayers(), loadGames(), loadStats()]);
});
</script>
