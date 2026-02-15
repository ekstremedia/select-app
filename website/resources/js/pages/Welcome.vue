<template>
    <div class="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">
        <!-- Top bar -->
        <nav class="flex items-center justify-end gap-3 px-4 py-3 sm:px-6 flex-wrap">
            <button
                @click="toggleLocale"
                class="w-9 h-9 inline-flex items-center justify-center text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                :title="isNorwegian ? 'Switch to English' : 'Bytt til norsk'"
            >
                {{ t('nav.language') }}
            </button>
            <button
                @click="toggleDark"
                class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                :title="isDark ? t('nav.lightMode') : t('nav.darkMode')"
            >
                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </nav>

        <!-- Hero section -->
        <section class="flex flex-col items-center px-4 pt-2 pb-16 sm:pt-20 sm:pb-24 text-center">
            <h1 class="text-6xl sm:text-8xl font-bold tracking-[0.3em] text-emerald-600 dark:text-emerald-400 mb-2">
                SELECT
            </h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mb-8">
                {{ t('hero.subtitle') }}
            </p>

            <!-- Animated acronym demo -->
            <div class="flex gap-2 sm:gap-3 mb-6">
                <span
                    v-for="(letter, i) in acronymLetters"
                    :key="`${animationKey}-${i}`"
                    class="inline-flex items-center justify-center w-10 h-10 sm:w-14 sm:h-14 rounded-lg text-lg sm:text-2xl font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border-2 border-emerald-300 dark:border-emerald-700 transition-all duration-500"
                    :style="{ transitionDelay: `${i * 100}ms`, opacity: visibleLetters > i ? 1 : 0, transform: visibleLetters > i ? 'translateY(0)' : 'translateY(-10px)' }"
                >
                    {{ letter }}
                </span>
            </div>

            <!-- Animated sentence -->
            <p
                class="text-lg sm:text-xl text-slate-600 dark:text-slate-400 mb-2 min-h-8 max-w-lg line-clamp-2 transition-opacity duration-500"
                :style="{ opacity: showSentence ? 1 : 0 }"
            >
                {{ currentSentence }}
            </p>
        </section>

        <!-- Play now CTA -->
        <section class="px-4 pb-12 sm:pb-16 text-center">
            <Button :label="t('cta.play')" severity="success" size="large" raised @click="router.visit('/spill')" />
        </section>

        <!-- How it works -->
        <section class="px-4 pb-16 sm:pb-24 max-w-4xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-center mb-10 text-slate-800 dark:text-slate-200">
                {{ t('how.title') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div
                    v-for="step in 4"
                    :key="step"
                    class="relative p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-500 text-white text-sm font-bold shrink-0">
                            {{ step }}
                        </span>
                        <h3 class="font-semibold text-lg text-slate-800 dark:text-slate-200">
                            {{ t(`how.step${step}.title`) }}
                        </h3>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 pl-11">
                        {{ t(`how.step${step}.desc`) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Stats section -->
        <section v-if="stats" class="px-4 pb-12 sm:pb-16">
            <div class="max-w-md mx-auto grid grid-cols-3 gap-4 text-center">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.games_played }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('stats.gamesPlayed') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.total_sentences }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('stats.sentences') }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active_players }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('stats.activePlayers') }}</p>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="px-4 py-8">
        </footer>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useI18n } from '../composables/useI18n.js';
import { useDarkMode } from '../composables/useDarkMode.js';
import { useAuthStore } from '../stores/authStore.js';
import { api } from '../services/api.js';

defineOptions({ layout: false });

const { t, toggleLocale, isNorwegian } = useI18n();
const { isDark, toggleDark } = useDarkMode();

// Use the initial gullkorn from Inertia for the first animation
const initialGullkorn = usePage().props.gullkorn || '';

const acronymLetters = ref([]);
const visibleLetters = ref(0);
const showSentence = ref(false);
const currentSentence = ref('');
const animationKey = ref(0);
const stats = ref(null);

let animationTimer = null;
let cycleTimeout = null;

function parseSentence(sentence) {
    const words = sentence.split(/\s+/).filter(w => w.length > 0);
    const letters = words
        .map(w => w.replace(/[^a-zA-ZæøåÆØÅ]/g, '').charAt(0).toUpperCase())
        .filter(l => l.length > 0);
    return {
        letters,
        text: words.join(' '),
    };
}

function animateWithSentence(sentence) {
    const parsed = parseSentence(sentence);
    acronymLetters.value = parsed.letters;
    animationKey.value++;
    visibleLetters.value = 0;
    showSentence.value = false;
    currentSentence.value = '';

    let count = 0;
    animationTimer = setInterval(() => {
        count++;
        if (count <= parsed.letters.length) {
            visibleLetters.value = count;
        } else if (count === parsed.letters.length + 2) {
            currentSentence.value = parsed.text;
            showSentence.value = true;
        } else if (count > parsed.letters.length + 8) {
            clearInterval(animationTimer);
            animationTimer = null;
            cycleTimeout = setTimeout(fetchAndAnimate, 2000);
        }
    }, 300);
}

async function fetchAndAnimate() {
    try {
        const { data } = await api.hallOfFame.random();
        if (data.sentence?.text) {
            animateWithSentence(data.sentence.text);
            return;
        }
    } catch {
        // Fallback to initial if API fails
    }

    if (initialGullkorn) {
        animateWithSentence(initialGullkorn);
    }
}

onMounted(async () => {
    const authStore = useAuthStore();
    if (!authStore.isInitialized) {
        await authStore.loadFromStorage();
    }

    // Start first animation with Inertia prop, then fetch new ones
    if (initialGullkorn) {
        animateWithSentence(initialGullkorn);
    } else {
        fetchAndAnimate();
    }

    api.stats().then(res => { stats.value = res.data; }).catch(() => {});
});

onUnmounted(() => {
    if (animationTimer) {
        clearInterval(animationTimer);
    }
    if (cycleTimeout) {
        clearTimeout(cycleTimeout);
    }
});
</script>
