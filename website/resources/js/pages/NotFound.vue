<template>
    <div class="flex flex-col items-center justify-center px-4 py-20 sm:py-32 text-center">
        <h1 class="text-6xl sm:text-8xl font-bold text-emerald-600 dark:text-emerald-400 mb-4">
            404
        </h1>
        <p class="text-xl sm:text-2xl font-semibold text-slate-800 dark:text-slate-200 mb-2">
            {{ t('common.notFound') }}
        </p>
        <p class="text-slate-500 dark:text-slate-400 mb-8 max-w-md">
            {{ t('common.notFoundDesc') }}
        </p>
        <Button
            :label="t('common.goHome')"
            severity="success"
            @click="router.visit('/')"
        />
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();
</script>
