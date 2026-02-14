import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'welcome',
        component: () => import('./pages/Welcome.vue'),
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/Login.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('./pages/Register.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('./pages/ForgotPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: () => import('./pages/ResetPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/profile',
        name: 'profile-settings',
        component: () => import('./pages/ProfileSettings.vue'),
        meta: { requiresRegistered: true },
    },
    {
        path: '/profile/:nickname',
        name: 'profile',
        component: () => import('./pages/Profile.vue'),
    },
    {
        path: '/games',
        name: 'games',
        component: () => import('./pages/Games.vue'),
        meta: { requiresPlayer: true },
    },
    {
        path: '/games/create',
        name: 'games-create',
        component: () => import('./pages/CreateGame.vue'),
        meta: { requiresPlayer: true },
    },
    {
        path: '/games/join',
        name: 'games-join',
        component: () => import('./pages/JoinGame.vue'),
        meta: { requiresPlayer: true },
    },
    {
        path: '/games/:code',
        name: 'game',
        component: () => import('./pages/Game.vue'),
        meta: { requiresPlayer: true },
    },
    {
        path: '/games/:code/spectate',
        name: 'game-spectate',
        component: () => import('./pages/GameSpectate.vue'),
    },
    {
        path: '/archive',
        name: 'archive',
        component: () => import('./pages/Archive.vue'),
    },
    {
        path: '/archive/:code',
        name: 'archive-game',
        component: () => import('./pages/ArchiveGame.vue'),
    },
    {
        path: '/leaderboard',
        name: 'leaderboard',
        component: () => import('./pages/Leaderboard.vue'),
    },
    {
        path: '/hall-of-fame',
        name: 'hall-of-fame',
        component: () => import('./pages/HallOfFame.vue'),
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('./pages/Admin.vue'),
        meta: { requiresAdmin: true },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./pages/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const { useAuthStore } = await import('./stores/authStore.js');
    const auth = useAuthStore();

    if (!auth.isInitialized) {
        await auth.loadFromStorage();
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'welcome' };
    }

    if (to.meta.requiresRegistered && !auth.user) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresPlayer && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.user) {
        return { name: 'welcome' };
    }

    return true;
});

export default router;
