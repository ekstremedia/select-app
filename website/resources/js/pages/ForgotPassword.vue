<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20">
        <h1 class="text-3xl font-bold text-center mb-4 text-slate-800 dark:text-slate-200">
            {{ t('auth.forgotPassword.title') }}
        </h1>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400 mb-8">
            {{ t('auth.forgotPassword.description') }}
        </p>

        <!-- Success message -->
        <div v-if="sent" class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-sm text-emerald-700 dark:text-emerald-300 text-center mb-6">
            {{ t('auth.forgotPassword.sent') }}
        </div>

        <form v-if="!sent" @submit.prevent="handleSubmit" class="space-y-5">
            <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ error }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.login.email') }}
                </label>
                <InputText
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    class="w-full"
                />
            </div>

            <Button
                type="submit"
                :label="t('auth.forgotPassword.submit')"
                severity="success"
                :loading="loading"
                class="w-full"
            />
        </form>

        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
            <Link href="/logg-inn" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                {{ t('common.back') }}
            </Link>
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();

const email = ref('');
const loading = ref(false);
const error = ref('');
const sent = ref(false);

async function handleSubmit() {
    if (!email.value.trim()) return;

    loading.value = true;
    error.value = '';

    try {
        await api.auth.forgotPassword(email.value.trim());
        sent.value = true;
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}
</script>
