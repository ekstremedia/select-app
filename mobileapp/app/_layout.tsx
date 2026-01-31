import { useEffect } from 'react';
import { Stack } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../src/stores/authStore';
import { ThemeProvider, useTheme } from '../src/theme';
import '../src/i18n';

function RootLayoutNav() {
  const { t } = useTranslation();
  const { theme } = useTheme();
  const loadSession = useAuthStore((state) => state.loadSession);

  useEffect(() => {
    loadSession();
  }, []);

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: theme.headerBackground },
        headerTintColor: theme.headerText,
        headerTitleStyle: { fontWeight: '600' },
        contentStyle: { backgroundColor: theme.background },
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Select', headerShown: false }} />
      <Stack.Screen name="create" options={{ title: t('game.createGame') }} />
      <Stack.Screen name="join" options={{ title: t('game.joinGame') }} />
      <Stack.Screen name="game/[code]" options={{ title: t('common.game') }} />
      <Stack.Screen name="profile" options={{ title: t('common.profile') }} />
      <Stack.Screen name="settings" options={{ title: t('settings.title') }} />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <ThemeProvider>
      <RootLayoutNav />
    </ThemeProvider>
  );
}
