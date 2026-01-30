import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Player, User } from '../types';
import { api } from '../services/api';
import { websocket } from '../services/websocket';

interface AuthState {
  player: Player | null;
  user: User | null;
  authToken: string | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  createGuest: (displayName: string) => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (email: string, password: string, name?: string) => Promise<void>;
  convertGuest: (email: string, password: string, name?: string) => Promise<void>;
  logout: () => void;
  loadSession: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      player: null,
      user: null,
      authToken: null,
      isLoading: false,
      error: null,

      createGuest: async (displayName: string) => {
        set({ isLoading: true, error: null });
        try {
          const response = await api.createGuest(displayName);
          const { player } = response;

          api.setGuestToken(player.guest_token || null);
          websocket.initialize(player.guest_token);

          set({
            player,
            user: null,
            authToken: null,
            isLoading: false,
          });
        } catch (error) {
          set({
            isLoading: false,
            error: error instanceof Error ? error.message : 'Failed to create guest',
          });
          throw error;
        }
      },

      login: async (email: string, password: string) => {
        set({ isLoading: true, error: null });
        try {
          const response = await api.login(email, password);
          const { player, user, token } = response;

          api.setAuthToken(token || null);
          api.setGuestToken(null);
          websocket.initialize(undefined, token);

          set({
            player,
            user: user || null,
            authToken: token || null,
            isLoading: false,
          });
        } catch (error) {
          set({
            isLoading: false,
            error: error instanceof Error ? error.message : 'Failed to login',
          });
          throw error;
        }
      },

      register: async (email: string, password: string, name?: string) => {
        set({ isLoading: true, error: null });
        try {
          const state = get();
          const guestToken = state.player?.guest_token;

          const response = await api.register(email, password, name, guestToken);
          const { player, user, token } = response;

          api.setAuthToken(token || null);
          api.setGuestToken(null);
          websocket.initialize(undefined, token);

          set({
            player,
            user: user || null,
            authToken: token || null,
            isLoading: false,
          });
        } catch (error) {
          set({
            isLoading: false,
            error: error instanceof Error ? error.message : 'Failed to register',
          });
          throw error;
        }
      },

      convertGuest: async (email: string, password: string, name?: string) => {
        set({ isLoading: true, error: null });
        try {
          const state = get();
          if (!state.player?.guest_token) {
            throw new Error('No guest token available');
          }

          const response = await api.convertGuest(
            state.player.guest_token,
            email,
            password,
            name
          );
          const { player, user, token } = response;

          api.setAuthToken(token || null);
          api.setGuestToken(null);
          websocket.initialize(undefined, token);

          set({
            player,
            user: user || null,
            authToken: token || null,
            isLoading: false,
          });
        } catch (error) {
          set({
            isLoading: false,
            error: error instanceof Error ? error.message : 'Failed to convert account',
          });
          throw error;
        }
      },

      logout: () => {
        api.setAuthToken(null);
        api.setGuestToken(null);
        websocket.disconnect();

        set({
          player: null,
          user: null,
          authToken: null,
          error: null,
        });
      },

      loadSession: async () => {
        const state = get();

        if (state.authToken) {
          api.setAuthToken(state.authToken);
          websocket.initialize(undefined, state.authToken);
        } else if (state.player?.guest_token) {
          api.setGuestToken(state.player.guest_token);
          websocket.initialize(state.player.guest_token);
        }
      },

      clearError: () => {
        set({ error: null });
      },
    }),
    {
      name: 'auth-storage',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        player: state.player,
        user: state.user,
        authToken: state.authToken,
      }),
    }
  )
);
