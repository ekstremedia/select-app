import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '../authStore.js';

vi.mock('../../services/api.js', () => ({
    api: {
        auth: {
            guest: vi.fn(),
            login: vi.fn(),
            register: vi.fn(),
            logout: vi.fn(),
            me: vi.fn(),
        },
    },
}));

import { api } from '../../services/api.js';

describe('authStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('isAuthenticated is false', () => {
            const store = useAuthStore();
            expect(store.isAuthenticated).toBe(false);
        });

        it('isGuest is true when no player', () => {
            const store = useAuthStore();
            expect(store.isGuest).toBe(true);
        });

        it('isAdmin is false when no user', () => {
            const store = useAuthStore();
            expect(store.isAdmin).toBe(false);
        });

        it('player, user, and token are null', () => {
            const store = useAuthStore();
            expect(store.player).toBeNull();
            expect(store.user).toBeNull();
            expect(store.token).toBeNull();
        });

        it('nickname is null when no player', () => {
            const store = useAuthStore();
            expect(store.nickname).toBeNull();
        });
    });

    describe('createGuest', () => {
        it('sets player and saves guest_token to localStorage', async () => {
            const mockPlayer = {
                id: 'uuid-1',
                nickname: 'TestGuest',
                guest_token: 'guest-abc-123',
                is_guest: true,
            };
            api.auth.guest.mockResolvedValue({ data: { player: mockPlayer } });

            const store = useAuthStore();
            const result = await store.createGuest('TestGuest');

            expect(api.auth.guest).toHaveBeenCalledWith('TestGuest');
            expect(store.player).toEqual(mockPlayer);
            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(true);
            expect(store.isGuest).toBe(true);
            expect(store.nickname).toBe('TestGuest');
            expect(localStorage.getItem('select-guest-token')).toBe('guest-abc-123');
            expect(result).toEqual({ player: mockPlayer });
        });
    });

    describe('login', () => {
        it('sets player, user, token and clears guest_token', async () => {
            localStorage.setItem('select-guest-token', 'old-guest-token');

            const mockData = {
                player: { id: 'uuid-2', nickname: 'LoginUser', is_guest: false },
                user: { id: 1, name: 'Login User', role: 'user' },
                token: 'auth-token-xyz',
            };
            api.auth.login.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            const result = await store.login('test@example.com', 'password123');

            expect(api.auth.login).toHaveBeenCalledWith({ email: 'test@example.com', password: 'password123' });
            expect(store.player).toEqual(mockData.player);
            expect(store.user).toEqual(mockData.user);
            expect(store.token).toBe('auth-token-xyz');
            expect(store.isAuthenticated).toBe(true);
            expect(store.isGuest).toBe(false);
            expect(localStorage.getItem('select-auth-token')).toBe('auth-token-xyz');
            expect(localStorage.getItem('select-guest-token')).toBeNull();
            expect(result).toEqual(mockData);
        });

        it('passes two-factor code when provided', async () => {
            const mockData = {
                player: { id: 'uuid-3', nickname: 'TwoFactor', is_guest: false },
                user: { id: 2, role: 'user' },
                token: 'token-2fa',
            };
            api.auth.login.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            await store.login('test@example.com', 'pass', '123456');

            expect(api.auth.login).toHaveBeenCalledWith({
                email: 'test@example.com',
                password: 'pass',
                two_factor_code: '123456',
            });
        });
    });

    describe('register', () => {
        it('sets player, user, token and clears guest_token', async () => {
            localStorage.setItem('select-guest-token', 'old-guest-token');

            const mockData = {
                player: { id: 'uuid-4', nickname: 'NewUser', is_guest: false },
                user: { id: 3, role: 'user' },
                token: 'reg-token',
            };
            api.auth.register.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            const formData = { name: 'New', email: 'new@test.com', password: 'secret', nickname: 'NewUser' };
            await store.register(formData);

            expect(api.auth.register).toHaveBeenCalledWith(formData);
            expect(store.player).toEqual(mockData.player);
            expect(store.user).toEqual(mockData.user);
            expect(store.token).toBe('reg-token');
            expect(localStorage.getItem('select-auth-token')).toBe('reg-token');
            expect(localStorage.getItem('select-guest-token')).toBeNull();
        });
    });

    describe('logout', () => {
        it('clears all state and localStorage', async () => {
            api.auth.logout.mockResolvedValue({});
            api.auth.guest.mockResolvedValue({
                data: { player: { id: 'uuid-5', nickname: 'ToLogout', guest_token: 'gt', is_guest: true } },
            });

            const store = useAuthStore();
            await store.createGuest('ToLogout');
            expect(store.isAuthenticated).toBe(true);

            await store.logout();

            expect(store.player).toBeNull();
            expect(store.user).toBeNull();
            expect(store.token).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(localStorage.getItem('select-auth-token')).toBeNull();
            expect(localStorage.getItem('select-guest-token')).toBeNull();
        });

        it('clears state even if api.auth.logout throws', async () => {
            api.auth.logout.mockRejectedValue(new Error('Network error'));

            const store = useAuthStore();
            store.player = { id: 'uuid-6' };

            await store.logout();

            expect(store.player).toBeNull();
            expect(store.user).toBeNull();
            expect(store.token).toBeNull();
        });
    });

    describe('clearAuth', () => {
        it('clears all state and localStorage synchronously', () => {
            const store = useAuthStore();
            store.player = { id: 'uuid-7', nickname: 'ClearMe', is_guest: true };
            store.user = { id: 4, role: 'user' };
            store.token = 'some-token';
            localStorage.setItem('select-auth-token', 'some-token');
            localStorage.setItem('select-guest-token', 'some-guest');

            store.clearAuth();

            expect(store.player).toBeNull();
            expect(store.user).toBeNull();
            expect(store.token).toBeNull();
            expect(localStorage.getItem('select-auth-token')).toBeNull();
            expect(localStorage.getItem('select-guest-token')).toBeNull();
        });
    });

    describe('computed properties', () => {
        it('isGuest returns false when player is not a guest', () => {
            const store = useAuthStore();
            store.player = { id: 'uuid-8', is_guest: false };

            expect(store.isGuest).toBe(false);
        });

        it('isGuest returns true when player is a guest', () => {
            const store = useAuthStore();
            store.player = { id: 'uuid-9', is_guest: true };

            expect(store.isGuest).toBe(true);
        });

        it('isAdmin returns true when user role is admin', () => {
            const store = useAuthStore();
            store.user = { id: 1, role: 'admin' };

            expect(store.isAdmin).toBe(true);
        });

        it('isAdmin returns false when user role is not admin', () => {
            const store = useAuthStore();
            store.user = { id: 2, role: 'user' };

            expect(store.isAdmin).toBe(false);
        });

        it('nickname returns player nickname', () => {
            const store = useAuthStore();
            store.player = { id: 'uuid-10', nickname: 'TestNick' };

            expect(store.nickname).toBe('TestNick');
        });
    });

    describe('fetchMe', () => {
        it('sets player and user from response', async () => {
            const mockData = {
                player: { id: 'uuid-11', nickname: 'FetchedUser', is_guest: false },
                user: { id: 5, role: 'user' },
            };
            api.auth.me.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            const result = await store.fetchMe();

            expect(store.player).toEqual(mockData.player);
            expect(store.user).toEqual(mockData.user);
            expect(result).toEqual(mockData);
        });

        it('clears player and user on error', async () => {
            api.auth.me.mockRejectedValue(new Error('Unauthorized'));

            const store = useAuthStore();
            store.player = { id: 'uuid-12' };
            store.user = { id: 6 };

            const result = await store.fetchMe();

            expect(store.player).toBeNull();
            expect(store.user).toBeNull();
            expect(result).toBeNull();
        });
    });

    describe('loadFromStorage', () => {
        it('restores auth token and fetches me', async () => {
            localStorage.setItem('select-auth-token', 'stored-token');
            const mockData = {
                player: { id: 'uuid-13', is_guest: false },
                user: { id: 7 },
            };
            api.auth.me.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            await store.loadFromStorage();

            expect(store.token).toBe('stored-token');
            expect(store.isInitialized).toBe(true);
            expect(api.auth.me).toHaveBeenCalled();
        });

        it('sets isInitialized even with no stored tokens', async () => {
            const store = useAuthStore();
            await store.loadFromStorage();

            expect(store.isInitialized).toBe(true);
            expect(api.auth.me).not.toHaveBeenCalled();
        });

        it('fetches me when guest token exists', async () => {
            localStorage.setItem('select-guest-token', 'guest-token');
            const mockData = {
                player: { id: 'uuid-14', is_guest: true },
            };
            api.auth.me.mockResolvedValue({ data: mockData });

            const store = useAuthStore();
            await store.loadFromStorage();

            expect(store.isInitialized).toBe(true);
            expect(api.auth.me).toHaveBeenCalled();
            expect(store.player).toEqual(mockData.player);
        });
    });
});
