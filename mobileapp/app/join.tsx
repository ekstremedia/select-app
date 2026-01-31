import { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useGameStore } from '../src/stores/gameStore';
import { useAuthStore } from '../src/stores/authStore';
import { useTheme } from '../src/theme';

export default function JoinGameScreen() {
  const router = useRouter();
  const { t } = useTranslation();
  const { theme } = useTheme();
  const { joinGame, isLoading, error, clearError } = useGameStore();
  const player = useAuthStore((state) => state.player);

  const [code, setCode] = useState('');

  const handleJoin = async () => {
    if (code.length !== 6) {
      return;
    }

    try {
      await joinGame(code);

      const game = useGameStore.getState().game;
      if (game) {
        router.replace(`/game/${game.code}`);
      }
    } catch (err) {
      // Error handled by store
    }
  };

  const handleCodeChange = (text: string) => {
    // Only allow alphanumeric characters and uppercase
    const cleaned = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    setCode(cleaned.slice(0, 6));
  };

  if (!player) {
    router.replace('/');
    return null;
  }

  const styles = createStyles(theme);

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('join.enterCode')}</Text>
      <Text style={styles.subtitle}>{t('join.askHost')}</Text>

      <View style={styles.inputContainer}>
        <TextInput
          style={styles.codeInput}
          value={code}
          onChangeText={handleCodeChange}
          placeholder="ABCDEF"
          placeholderTextColor={theme.inputPlaceholder}
          maxLength={6}
          autoCapitalize="characters"
          autoCorrect={false}
          autoFocus
        />

        {error && <Text style={styles.errorText}>{error}</Text>}

        <TouchableOpacity
          style={[
            styles.joinButton,
            (code.length !== 6 || isLoading) && styles.disabledButton,
          ]}
          onPress={handleJoin}
          disabled={code.length !== 6 || isLoading}
        >
          {isLoading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.joinButtonText}>{t('game.joinGame')}</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.background,
      padding: 24,
      justifyContent: 'center',
    },
    title: {
      fontSize: 28,
      fontWeight: 'bold',
      color: theme.text,
      textAlign: 'center',
      marginBottom: 8,
    },
    subtitle: {
      fontSize: 16,
      color: theme.textSecondary,
      textAlign: 'center',
      marginBottom: 32,
    },
    inputContainer: {
      gap: 16,
    },
    codeInput: {
      backgroundColor: theme.surface,
      borderWidth: 2,
      borderColor: theme.primary,
      borderRadius: 16,
      padding: 20,
      fontSize: 32,
      fontWeight: 'bold',
      textAlign: 'center',
      letterSpacing: 8,
      color: theme.text,
    },
    joinButton: {
      backgroundColor: theme.buttonPrimaryBackground,
      borderRadius: 12,
      padding: 16,
      alignItems: 'center',
    },
    joinButtonText: {
      color: theme.buttonPrimaryText,
      fontSize: 18,
      fontWeight: '600',
    },
    disabledButton: {
      opacity: 0.5,
    },
    errorText: {
      color: theme.error,
      fontSize: 14,
      textAlign: 'center',
    },
  });
