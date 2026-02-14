<template>
    <div class="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">
        <!-- Navigation -->
        <nav class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14">
                    <!-- Left: Logo + Nav links -->
                    <div class="flex items-center gap-6">
                        <router-link to="/" class="text-xl font-bold tracking-widest text-emerald-600 dark:text-emerald-400">
                            SELECT
                        </router-link>
                        <div class="hidden sm:flex items-center gap-4">
                            <router-link
                                v-if="isAuthenticated"
                                to="/games"
                                class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                active-class="!text-emerald-600 dark:!text-emerald-400"
                            >
                                {{ t('nav.play') }}
                            </router-link>
                            <router-link
                                to="/archive"
                                class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                active-class="!text-emerald-600 dark:!text-emerald-400"
                            >
                                {{ t('nav.archive') }}
                            </router-link>
                            <router-link
                                to="/hall-of-fame"
                                class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                active-class="!text-emerald-600 dark:!text-emerald-400"
                            >
                                {{ t('nav.hallOfFame') }}
                            </router-link>
                            <router-link
                                to="/leaderboard"
                                class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                active-class="!text-emerald-600 dark:!text-emerald-400"
                            >
                                {{ t('nav.leaderboard') }}
                            </router-link>
                        </div>
                    </div>

                    <!-- Right: User menu + toggles -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleLocale"
                            class="px-2 py-1 text-xs font-medium rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            {{ t('nav.language') }}
                        </button>
                        <button
                            @click="toggleDark"
                            class="p-1.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        </button>

                        <!-- User section -->
                        <template v-if="isAuthenticated">
                            <router-link
                                v-if="!isGuest"
                                :to="{ name: 'profile', params: { nickname: authNickname } }"
                                class="px-3 py-1 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                            >
                                {{ authNickname }}
                            </router-link>
                            <span v-else class="px-2 text-sm text-slate-500">{{ authNickname }}</span>
                            <Button
                                v-if="isGuest"
                                :label="t('nav.createAccount')"
                                size="small"
                                severity="success"
                                variant="outlined"
                                @click="$router.push('/register')"
                            />
                            <Button
                                v-else
                                :label="t('nav.logout')"
                                size="small"
                                severity="secondary"
                                variant="text"
                                @click="handleLogout"
                            />
                        </template>
                        <template v-else>
                            <Button
                                :label="t('nav.login')"
                                size="small"
                                severity="secondary"
                                variant="text"
                                @click="$router.push('/login')"
                            />
                            <Button
                                :label="t('nav.register')"
                                size="small"
                                severity="success"
                                variant="outlined"
                                @click="$router.push('/register')"
                            />
                        </template>

                        <!-- Mobile menu toggle -->
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="sm:hidden p-1.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile menu -->
                <div v-if="mobileMenuOpen" class="sm:hidden pb-3 border-t border-slate-200 dark:border-slate-800 mt-2 pt-3">
                    <div class="flex flex-col gap-2">
                        <router-link v-if="isAuthenticated" to="/games" class="px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileMenuOpen = false">{{ t('nav.play') }}</router-link>
                        <router-link to="/archive" class="px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileMenuOpen = false">{{ t('nav.archive') }}</router-link>
                        <router-link to="/hall-of-fame" class="px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileMenuOpen = false">{{ t('nav.hallOfFame') }}</router-link>
                        <router-link to="/leaderboard" class="px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileMenuOpen = false">{{ t('nav.leaderboard') }}</router-link>
                        <router-link v-if="isAdmin" to="/admin" class="px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-red-500" @click="mobileMenuOpen = false">Admin</router-link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Guest banner -->
        <div v-if="isAuthenticated && isGuest" class="bg-emerald-50 dark:bg-emerald-950/50 border-b border-emerald-200 dark:border-emerald-900 py-2 px-4 text-center text-sm text-emerald-700 dark:text-emerald-300">
            {{ t('guest.banner') }}
            <router-link to="/register" class="font-medium underline hover:no-underline ml-1">{{ t('guest.createAccount') }}</router-link>
        </div>

        <!-- Page content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="px-4 py-8 text-center border-t border-slate-200 dark:border-slate-800 mt-auto">
            <p class="text-sm text-slate-400 dark:text-slate-500">
                {{ t('footer.tagline') }}
            </p>
        </footer>

        <Toast />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';
import { useDarkMode } from '../composables/useDarkMode.js';

const router = useRouter();
const authStore = useAuthStore();
const { isAuthenticated, isGuest, isAdmin, nickname: authNickname } = storeToRefs(authStore);
const { t, toggleLocale } = useI18n();
const { isDark, toggleDark } = useDarkMode();

const mobileMenuOpen = ref(false);

async function handleLogout() {
    await authStore.logout();
    router.push('/');
}
</script>
