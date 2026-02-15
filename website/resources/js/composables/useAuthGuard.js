import { router } from '@inertiajs/vue3';
import { useAuthStore } from '../stores/authStore.js';

const guestOnlyPaths = ['/login', '/register', '/forgot-password', '/reset-password'];
const requiresPlayerPaths = ['/games'];
const requiresRegisteredPaths = ['/profile'];
const requiresAdminPaths = ['/admin'];

function matchesAny(url, paths) {
    return paths.some(p => url === p || url.startsWith(p + '/') || url.startsWith(p + '?'));
}

export function setupAuthGuard() {
    router.on('before', async (event) => {
        const url = new URL(event.detail.visit.url).pathname;
        const auth = useAuthStore();

        if (!auth.isInitialized) {
            await auth.loadFromStorage();
        }

        if (matchesAny(url, requiresAdminPaths) && !auth.isAdmin) {
            event.preventDefault();
            router.visit('/');
            return;
        }

        // /profile exactly (settings) requires registered user
        if (url === '/profile' && !auth.user) {
            event.preventDefault();
            router.visit(`/login?redirect=${encodeURIComponent(url)}`);
            return;
        }

        if (matchesAny(url, requiresPlayerPaths) && !auth.isAuthenticated) {
            event.preventDefault();
            router.visit(`/login?redirect=${encodeURIComponent(url)}`);
            return;
        }

        if (matchesAny(url, guestOnlyPaths) && auth.user) {
            event.preventDefault();
            router.visit('/');
            return;
        }
    });
}
