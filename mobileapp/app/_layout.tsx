import { useEffect } from 'react';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useAuthStore } from '../src/stores/authStore';

export default function RootLayout() {
  const loadSession = useAuthStore((state) => state.loadSession);

  useEffect(() => {
    loadSession();
  }, []);

  return (
    <>
      <StatusBar style="dark" />
      <Stack
        screenOptions={{
          headerStyle: {
            backgroundColor: '#6366f1',
          },
          headerTintColor: '#fff',
          headerTitleStyle: {
            fontWeight: 'bold',
          },
        }}
      >
        <Stack.Screen
          name="index"
          options={{
            title: 'Gullkorn',
          }}
        />
        <Stack.Screen
          name="create"
          options={{
            title: 'Create Game',
          }}
        />
        <Stack.Screen
          name="join"
          options={{
            title: 'Join Game',
          }}
        />
        <Stack.Screen
          name="game/[code]"
          options={{
            title: 'Game',
            headerBackVisible: false,
          }}
        />
        <Stack.Screen
          name="profile"
          options={{
            title: 'Profile',
          }}
        />
      </Stack>
    </>
  );
}
