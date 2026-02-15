<template>
    <div class="flex flex-col items-center justify-center px-4 py-20 sm:py-32 text-center">
        <h1 class="text-6xl sm:text-8xl font-bold text-emerald-600 dark:text-emerald-400 mb-4">
            {{ status }}
        </h1>
        <p class="text-xl sm:text-2xl font-semibold text-slate-800 dark:text-slate-200 mb-2">
            {{ title }}
        </p>
        <p class="text-slate-500 dark:text-slate-400 mb-8 max-w-md">
            {{ description }}
        </p>
        <Button
            :label="t('common.goHome')"
            severity="success"
            @click="router.visit('/')"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useI18n } from '../composables/useI18n.js';

const props = defineProps({ status: Number });
const { t } = useI18n();

const title = computed(() => {
    const titles = {
        403: t('common.forbidden'),
        404: t('common.notFound'),
        500: t('common.serverError'),
        503: t('common.serviceUnavailable'),
    };
    return titles[props.status] || t('common.error');
});

const description = computed(() => {
    const descriptions = {
        403: t('common.forbiddenDesc'),
        404: t('common.notFoundDesc'),
        500: t('common.serverErrorDesc'),
        503: t('common.serviceUnavailableDesc'),
    };
    return descriptions[props.status] || t('common.error');
});
</script>
