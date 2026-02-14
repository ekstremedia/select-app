<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20 text-center">
        <h1 class="text-3xl font-bold mb-4 text-slate-800 dark:text-slate-200">
            {{ t('games.joinByCode') }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-8">
            {{ t('games.enterCode') }}
        </p>

        <form @submit.prevent="handleJoin" class="space-y-6">
            <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ error }}
            </div>

            <!-- Code input -->
            <div class="flex justify-center gap-2">
                <InputText
                    ref="codeInput"
                    v-model="code"
                    maxlength="6"
                    class="text-center text-3xl sm:text-4xl tracking-[0.4em] font-mono font-bold uppercase w-full max-w-xs"
                    :placeholder="'______'"
                    @input="handleInput"
                />
            </div>

            <p class="text-sm text-slate-400 dark:text-slate-500">
                {{ code.length }}/6
            </p>

            <Button
                type="submit"
                :label="t('games.join')"
                severity="success"
                size="large"
                :disabled="code.length !== 6"
                :loading="loading"
                class="w-full max-w-xs"
            />
        </form>

        <p class="mt-8">
            <router-link to="/games" class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline">
                {{ t('common.back') }}
            </router-link>
        </p>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useGameStore } from '../stores/gameStore.js';
import { useI18n } from '../composables/useI18n.js';

const router = useRouter();
const route = useRoute();
const gameStore = useGameStore();
const { t } = useI18n();

const code = ref('');
const loading = ref(false);
const error = ref('');
const codeInput = ref(null);

function handleInput() {
    code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (code.value.length === 6) {
        handleJoin();
    }
}

async function handleJoin() {
    if (code.value.length !== 6) return;

    loading.value = true;
    error.value = '';

    try {
        await gameStore.joinGame(code.value);
        router.push(`/games/${code.value}`);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    // Pre-fill from query params if present
    if (route.query.code) {
        code.value = String(route.query.code).toUpperCase().slice(0, 6);
    }
    codeInput.value?.$el?.focus?.();
});
</script>
