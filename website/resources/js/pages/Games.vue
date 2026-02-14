<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-8 text-slate-800 dark:text-slate-200">
            {{ t('games.title') }}
        </h1>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Actions panel -->
            <div class="space-y-4">
                <Button
                    :label="t('games.create')"
                    severity="success"
                    size="large"
                    class="w-full"
                    @click="router.push('/games/create')"
                />

                <!-- Join by code -->
                <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                    <h2 class="text-lg font-semibold mb-3 text-slate-800 dark:text-slate-200">
                        {{ t('games.joinByCode') }}
                    </h2>
                    <form @submit.prevent="handleJoinByCode" class="flex gap-2">
                        <InputText
                            v-model="joinCode"
                            :placeholder="t('games.enterCode')"
                            maxlength="6"
                            class="flex-1 text-center uppercase tracking-[0.3em] font-mono text-lg"
                            @input="joinCode = joinCode.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                        />
                        <Button
                            type="submit"
                            :label="t('games.join')"
                            severity="success"
                            :disabled="joinCode.length !== 6"
                        />
                    </form>
                    <small v-if="joinError" class="text-red-500 mt-2 block">{{ joinError }}</small>
                </div>
            </div>

            <!-- Open games list -->
            <div>
                <h2 class="text-lg font-semibold mb-3 text-slate-800 dark:text-slate-200">
                    {{ t('games.openGames') }}
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
                            <div>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tracking-widest">
                                    #{{ game.code }}
                                </span>
                                <span class="ml-3 text-sm text-slate-500 dark:text-slate-400">
                                    {{ game.host_nickname }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <Badge :value="`${game.player_count}/${game.max_players ?? 8}`" />
                                <span class="text-xs text-slate-400">
                                    {{ game.rounds ?? 5 }} {{ t('games.rounds') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Badge from 'primevue/badge';
import { api } from '../services/api.js';
import { useGameStore } from '../stores/gameStore.js';
import { useI18n } from '../composables/useI18n.js';

const router = useRouter();
const gameStore = useGameStore();
const { t } = useI18n();

const openGames = ref([]);
const loading = ref(true);
const joinCode = ref('');
const joinError = ref('');

async function loadGames() {
    loading.value = true;
    try {
        const { data } = await api.games.list({ status: 'waiting' });
        openGames.value = data.games ?? data.data ?? [];
    } catch {
        openGames.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleJoin(code) {
    joinError.value = '';
    try {
        await gameStore.joinGame(code);
        router.push(`/games/${code}`);
    } catch (err) {
        joinError.value = err.response?.data?.message || t('common.error');
    }
}

async function handleJoinByCode() {
    if (joinCode.value.length !== 6) return;
    await handleJoin(joinCode.value);
}

onMounted(loadGames);
</script>
