<template>
    <div class="flex flex-col bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100" :style="{ height: `${viewportHeight}px` }">
        <!-- Minimal game header -->
        <header class="flex items-center justify-between px-4 py-2 border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-3">
                <Link href="/spill" class="text-sm font-bold tracking-widest text-emerald-600 dark:text-emerald-400">
                    SELECT
                </Link>
                <span class="text-sm font-mono font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1">
                    <svg v-if="isPrivate" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>
                    #{{ gameCode }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500">
                    {{ playerCount }} {{ t(playerCount === 1 ? 'common.player' : 'common.players') }}
                </span>
                <Button
                    :label="leaveLabel || t('game.leave')"
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
    leaveLabel: { type: String, default: '' },
    isPrivate: { type: Boolean, default: false },
});

defineEmits(['leave']);
</script>
