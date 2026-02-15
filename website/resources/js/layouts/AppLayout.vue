<template>
    <div class="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">
        <!-- Navigation -->
        <nav class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14">
                    <!-- Left: Logo + Nav links -->
                    <div class="flex items-center gap-6">
                        <Link href="/" class="text-xl font-bold tracking-widest text-emerald-600 dark:text-emerald-400">
                            SELECT
                        </Link>
                        <div class="hidden sm:flex items-center gap-4">
                            <Link
                                v-if="isAuthenticated"
                                href="/spill"
                                class="text-sm font-medium transition-colors"
                                :class="isActive('/spill') ? 'text-emerald-600! dark:text-emerald-400!' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400'"
                            >
                                {{ t('nav.play') }}
                            </Link>
                            <Link
                                href="/arkiv"
                                class="text-sm font-medium transition-colors"
                                :class="isActive('/arkiv') ? 'text-emerald-600! dark:text-emerald-400!' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400'"
                            >
                                {{ t('nav.archive') }}
                            </Link>
                            <Link
                                href="/hall-of-fame"
                                class="text-sm font-medium transition-colors"
                                :class="isActive('/hall-of-fame') ? 'text-emerald-600! dark:text-emerald-400!' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400'"
                            >
                                {{ t('nav.hallOfFame') }}
                            </Link>
                            <Link
                                href="/toppliste"
                                class="text-sm font-medium transition-colors"
                                :class="isActive('/toppliste') ? 'text-emerald-600! dark:text-emerald-400!' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400'"
                            >
                                {{ t('nav.leaderboard') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Right: User section + menu toggle -->
                    <div class="flex items-center gap-2">
                        <!-- User section -->
                        <template v-if="isAuthenticated">
                            <Link
                                v-if="!isGuest"
                                :href="`/profil/${authNickname}`"
                                class="px-3 py-1 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                            >
                                {{ authNickname }}
                            </Link>
                            <span v-else class="px-2 text-sm text-slate-500">{{ authNickname }}</span>
                        </template>

                        <!-- Menu toggle -->
                        <button
                            @click="menuOpen = !menuOpen"
                            class="p-1.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <svg v-if="!menuOpen" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Dropdown menu -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2 max-h-0"
                    enter-to-class="opacity-100 translate-y-0 max-h-96"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 max-h-96"
                    leave-to-class="opacity-0 -translate-y-2 max-h-0"
                >
                    <div v-if="menuOpen" class="overflow-hidden border-t border-slate-200 dark:border-slate-800 mt-2 pb-3 pt-3">
                        <div class="flex flex-col gap-1">
                            <!-- Nav links (mobile only) -->
                            <div class="sm:hidden flex flex-col gap-1">
                                <Link v-if="isAuthenticated" href="/spill" class="px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" @click="menuOpen = false">{{ t('nav.play') }}</Link>
                                <Link href="/arkiv" class="px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" @click="menuOpen = false">{{ t('nav.archive') }}</Link>
                                <Link href="/hall-of-fame" class="px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" @click="menuOpen = false">{{ t('nav.hallOfFame') }}</Link>
                                <Link href="/toppliste" class="px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" @click="menuOpen = false">{{ t('nav.leaderboard') }}</Link>
                                <Link v-if="isAdmin" href="/admin" class="px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-red-500 transition-colors" @click="menuOpen = false">Admin</Link>
                                <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
                            </div>

                            <!-- Settings row -->
                            <div class="flex items-center gap-2 px-3 py-2">
                                <button
                                    @click="toggleLocale"
                                    class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    {{ t('nav.language') }}
                                </button>
                                <button
                                    @click="toggleDark"
                                    class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                    {{ isDark ? t('nav.lightMode') : t('nav.darkMode') }}
                                </button>
                            </div>

                            <!-- Auth actions -->
                            <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
                            <div class="flex items-center gap-2 px-3 py-1">
                                <template v-if="isAuthenticated">
                                    <Button
                                        v-if="isGuest"
                                        :label="t('nav.createAccount')"
                                        size="small"
                                        severity="success"
                                        variant="outlined"
                                        @click="navigateTo('/registrer'); menuOpen = false"
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
                                        @click="navigateTo('/logg-inn'); menuOpen = false"
                                    />
                                    <Button
                                        :label="t('nav.register')"
                                        size="small"
                                        severity="success"
                                        variant="outlined"
                                        @click="navigateTo('/registrer'); menuOpen = false"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </nav>

        <!-- Guest banner -->
        <div v-if="isAuthenticated && isGuest" class="bg-emerald-50 dark:bg-emerald-950/50 border-b border-emerald-200 dark:border-emerald-900 py-2 px-4 text-center text-sm text-emerald-700 dark:text-emerald-300">
            {{ t('guest.banner') }}
            <Link href="/registrer" class="font-medium underline hover:no-underline ml-1">{{ t('guest.createAccount') }}</Link>
            <span class="mx-1">{{ t('auth.or') }}</span>
            <Link href="/logg-inn" class="font-medium underline hover:no-underline">{{ t('nav.login') }}</Link>
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
        <ConfirmDialog />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';
import { useDarkMode } from '../composables/useDarkMode.js';

const authStore = useAuthStore();
const { isAuthenticated, isGuest, isAdmin, nickname: authNickname } = storeToRefs(authStore);
const { t, toggleLocale } = useI18n();
const { isDark, toggleDark } = useDarkMode();

const menuOpen = ref(false);

function isActive(path) {
    const url = usePage().url.split('?')[0];
    return url === path || url.startsWith(path + '/');
}

function navigateTo(path) {
    router.visit(path);
}

async function handleLogout() {
    menuOpen.value = false;
    await authStore.logout();
    router.visit('/');
}

onMounted(async () => {
    if (!authStore.isInitialized) {
        await authStore.loadFromStorage();
    }
});
</script>
