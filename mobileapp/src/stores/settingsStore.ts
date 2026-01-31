import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import i18n from '../i18n';

export type Language = 'nb' | 'nn' | 'en';
export type ThemeMode = 'dark' | 'light';

interface SettingsState {
  language: Language;
  theme: ThemeMode;

  // Actions
  setLanguage: (language: Language) => void;
  setTheme: (theme: ThemeMode) => void;
  toggleTheme: () => void;
}

export const useSettingsStore = create<SettingsState>()(
  persist(
    (set, get) => ({
      language: 'nb',
      theme: 'dark',

      setLanguage: (language: Language) => {
        i18n.changeLanguage(language);
        set({ language });
      },

      setTheme: (theme: ThemeMode) => {
        set({ theme });
      },

      toggleTheme: () => {
        const currentTheme = get().theme;
        set({ theme: currentTheme === 'dark' ? 'light' : 'dark' });
      },
    }),
    {
      name: 'settings-storage',
      storage: createJSONStorage(() => AsyncStorage),
      onRehydrateStorage: () => (state) => {
        // Apply saved language when store is rehydrated
        if (state?.language) {
          i18n.changeLanguage(state.language);
        }
      },
    }
  )
);
