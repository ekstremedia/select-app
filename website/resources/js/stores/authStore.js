import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '../services/api.js';

export const useAuthStore = defineStore('auth', () => {
    const player = ref(null);
    const user = ref(null);
    const token = ref(null);
    const isInitialized = ref(false);

    const isAuthenticated = computed(() => !!player.value);
    const isGuest = computed(() => player.value?.is_guest ?? true);
    const isAdmin = computed(() => user.value?.role === 'admin');
    const nickname = computed(() => player.value?.nickname ?? null);

    async function createGuest(nickname) {
        const { data } = await api.auth.guest(nickname);
        player.value = data.player;
        user.value = null;
        localStorage.setItem('select-guest-token', data.player.guest_token);
        return data;
    }

    async function login(email, password, twoFactorCode) {
        const payload = { email, password };
        if (twoFactorCode) {
            payload.two_factor_code = twoFactorCode;
        }
        const { data } = await api.auth.login(payload);
        player.value = data.player;
        user.value = data.user;
        token.value = data.token;
        localStorage.setItem('select-auth-token', data.token);
        localStorage.removeItem('select-guest-token');
        return data;
    }

    async function register(formData) {
        const { data } = await api.auth.register(formData);
        player.value = data.player;
        user.value = data.user;
        token.value = data.token;
        localStorage.setItem('select-auth-token', data.token);
        localStorage.removeItem('select-guest-token');
        return data;
    }

    async function convertGuest(formData) {
        const guestToken = localStorage.getItem('select-guest-token');
        const { data } = await api.auth.convert({ ...formData, guest_token: guestToken });
        player.value = data.player;
        user.value = data.user;
        token.value = data.token;
        localStorage.setItem('select-auth-token', data.token);
        localStorage.removeItem('select-guest-token');
        return data;
    }

    async function logout() {
        try {
            await api.auth.logout();
        } catch {
            // Ignore errors on logout
        }
        player.value = null;
        user.value = null;
        token.value = null;
        localStorage.removeItem('select-auth-token');
        localStorage.removeItem('select-guest-token');
    }

    async function fetchMe() {
        try {
            const { data } = await api.auth.me();
            player.value = data.player;
            user.value = data.user ?? null;
            return data;
        } catch {
            player.value = null;
            user.value = null;
            return null;
        }
    }

    async function loadFromStorage() {
        const savedToken = localStorage.getItem('select-auth-token');
        const savedGuestToken = localStorage.getItem('select-guest-token');

        if (savedToken || savedGuestToken) {
            if (savedToken) {
                token.value = savedToken;
            }
            await fetchMe();
        }

        isInitialized.value = true;
    }

    function clearAuth() {
        player.value = null;
        user.value = null;
        token.value = null;
        localStorage.removeItem('select-auth-token');
        localStorage.removeItem('select-guest-token');
    }

    return {
        player,
        user,
        token,
        isInitialized,
        isAuthenticated,
        isGuest,
        isAdmin,
        nickname,
        createGuest,
        login,
        register,
        convertGuest,
        logout,
        fetchMe,
        loadFromStorage,
        clearAuth,
    };
});
