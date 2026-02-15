<template>
    <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-6 text-slate-800 dark:text-slate-200">
            {{ t('hallOfFame.title') }}
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
        <div v-if="loading && sentences.length === 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Skeleton v-for="i in 6" :key="i" height="8rem" />
        </div>

        <!-- Empty state -->
        <div v-else-if="sentences.length === 0" class="py-16 text-center">
            <p class="text-lg text-slate-500 dark:text-slate-400">{{ t('hallOfFame.noSentences') }}</p>
        </div>

        <!-- Sentence cards -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div
                v-for="sentence in sentences"
                :key="sentence.id"
                class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors"
            >
                <div class="flex justify-between items-start gap-2 mb-3">
                    <span class="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400 tracking-widest">
                        {{ sentence.acronym }}
                    </span>
                    <Badge :value="`${sentence.votes_count} ${t('game.votes')}`" severity="success" />
                </div>
                <p class="text-slate-800 dark:text-slate-200 mb-2">{{ sentence.text }}</p>
                <div class="flex items-center justify-between">
                    <Link
                        :href="`/profile/${sentence.player_nickname}`"
                        class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
                    >
                        {{ sentence.player_nickname }}
                    </Link>
                    <span v-if="sentence.game_code" class="text-xs text-slate-400">
                        <Link :href="`/archive/${sentence.game_code}`" class="hover:underline">
                            #{{ sentence.game_code }}
                        </Link>
                    </span>
                </div>
            </div>
        </div>

        <!-- Load more -->
        <div v-if="hasMore" class="text-center mb-10">
            <Button
                :label="t('archive.loadMore')"
                severity="secondary"
                variant="outlined"
                :loading="loading"
                @click="loadMore"
            />
        </div>

        <!-- IRC Classics section -->
        <section class="border-t border-slate-200 dark:border-slate-800 pt-8">
            <h2 class="text-2xl font-bold mb-6 text-slate-800 dark:text-slate-200">
                {{ t('hallOfFame.classic') }}
            </h2>

            <div v-if="classicLoading" class="space-y-4">
                <Skeleton height="5rem" />
            </div>

            <div v-else-if="classicSentence" class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                <p class="text-lg text-slate-800 dark:text-slate-200 mb-3 italic">
                    "{{ classicSentence.setning }}"
                </p>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        &mdash; {{ classicSentence.nick }}
                    </span>
                    <span class="text-xs text-slate-400">
                        {{ classicSentence.stemmer }} {{ t('game.votes') }}
                    </span>
                </div>
                <Button
                    label="Shuffle"
                    severity="secondary"
                    variant="text"
                    size="small"
                    class="mt-3"
                    @click="loadClassic"
                />
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Badge from 'primevue/badge';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const { t } = useI18n();

const sentences = ref([]);
const loading = ref(true);
const activePeriod = ref('all');
const page = ref(1);
const hasMore = ref(false);

const classicSentence = ref(null);
const classicLoading = ref(true);

const periods = computed(() => [
    { value: 'all', label: t('archive.allTime') },
    { value: 'month', label: t('archive.thisMonth') },
    { value: 'week', label: t('archive.thisWeek') },
]);

function changePeriod(period) {
    activePeriod.value = period;
    page.value = 1;
    sentences.value = [];
    loadSentences();
}

async function loadSentences() {
    loading.value = true;

    try {
        const params = {
            page: page.value,
            period: activePeriod.value !== 'all' ? activePeriod.value : undefined,
        };
        const { data } = await api.hallOfFame.list(params);
        const newItems = data.sentences ?? data.data ?? [];
        sentences.value = page.value === 1 ? newItems : [...sentences.value, ...newItems];
        hasMore.value = data.meta?.has_more ?? (data.next_page_url != null) ?? false;
    } catch {
        // Keep existing
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    page.value++;
    loadSentences();
}

async function loadClassic() {
    classicLoading.value = true;
    try {
        const { data } = await api.hallOfFame.random();
        classicSentence.value = data.gullkorn ?? data;
    } catch {
        // Keep existing
    } finally {
        classicLoading.value = false;
    }
}

onMounted(() => {
    loadSentences();
    loadClassic();
});
</script>
