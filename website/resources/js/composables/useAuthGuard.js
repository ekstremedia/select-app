import { router } from '@inertiajs/vue3';
import { useAuthStore } from '../stores/authStore.js';

const guestOnlyPaths = ['/logg-inn', '/registrer', '/glemt-passord', '/nytt-passord'];
const requiresPlayerPaths = ['/spill'];
const requiresAdminPaths = ['/admin'];

function matchesAny(url, paths) {
    return paths.some(p => url === p || url.startsWith(p + '/'));
}

export function setupAuthGuard() {
    router.on('before', (event) => {
        const url = new URL(event.detail.visit.url).pathname;
        const auth = useAuthStore();

        // Auth must be initialized before the guard runs (done in app.js)
        if (!auth.isInitialized) {
            return;
        }

        if (matchesAny(url, requiresAdminPaths) && !auth.isAdmin) {
            event.preventDefault();
            router.visit('/');
            return;
        }

        // /profil exactly (settings) requires registered user
        if (url === '/profil' && !auth.user) {
            event.preventDefault();
            router.visit(`/logg-inn?redirect=${encodeURIComponent(url)}`);
            return;
        }

        if (matchesAny(url, requiresPlayerPaths) && !auth.isAuthenticated) {
            event.preventDefault();
            router.visit(`/logg-inn?redirect=${encodeURIComponent(url)}`);
            return;
        }

        if (matchesAny(url, guestOnlyPaths) && auth.user) {
            event.preventDefault();
            router.visit('/');
            return;
        }
    });
}
