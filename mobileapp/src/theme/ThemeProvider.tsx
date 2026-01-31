import React, { createContext, useContext, useMemo } from 'react';
import { useSettingsStore } from '../stores/settingsStore';
import { lightTheme, darkTheme, Theme } from './colors';

interface ThemeContextType {
  theme: Theme;
  isDark: boolean;
}

const ThemeContext = createContext<ThemeContextType>({
  theme: darkTheme,
  isDark: true,
});

export const useTheme = () => useContext(ThemeContext);

interface ThemeProviderProps {
  children: React.ReactNode;
}

export const ThemeProvider: React.FC<ThemeProviderProps> = ({ children }) => {
  const themeMode = useSettingsStore((state) => state.theme);

  const value = useMemo(() => ({
    theme: themeMode === 'dark' ? darkTheme : lightTheme,
    isDark: themeMode === 'dark',
  }), [themeMode]);

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
};

export default ThemeProvider;
