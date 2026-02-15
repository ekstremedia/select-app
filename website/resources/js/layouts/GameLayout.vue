<template>
    <div class="flex flex-col bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100" :style="{ height: `${viewportHeight}px` }">
        <!-- Minimal game header -->
        <header class="flex items-center justify-between px-4 py-2 border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-3">
                <Link href="/games" class="text-sm font-bold tracking-widest text-emerald-600 dark:text-emerald-400">
                    SELECT
                </Link>
                <span class="text-sm font-mono font-bold text-slate-500 dark:text-slate-400">
                    #{{ gameCode }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500">
                    {{ playerCount }} {{ playerCount === 1 ? 'player' : 'players' }}
                </span>
                <Button
                    :label="t('game.leave')"
                    size="small"
                    severity="secondary"
                    variant="text"
                    @click="$emit('leave')"
                />
            </div>
        </header>

        <!-- Game content -->
        <main class="flex-1 overflow-hidden">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useViewport } from '../composables/useViewport.js';
import { useI18n } from '../composables/useI18n.js';

const { viewportHeight } = useViewport();
const { t } = useI18n();

defineProps({
    gameCode: { type: String, default: '' },
    playerCount: { type: Number, default: 0 },
});

defineEmits(['leave']);
</script>
