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
import { useGameStore } from '../src/stores/gameStore';
import { useAuthStore } from '../src/stores/authStore';

export default function JoinGameScreen() {
  const router = useRouter();
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

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Enter Game Code</Text>
      <Text style={styles.subtitle}>
        Ask the host for the 6-character game code
      </Text>

      <View style={styles.inputContainer}>
        <TextInput
          style={styles.codeInput}
          value={code}
          onChangeText={handleCodeChange}
          placeholder="ABCDEF"
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
            <Text style={styles.joinButtonText}>Join Game</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
    padding: 24,
    justifyContent: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1e293b',
    textAlign: 'center',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 32,
  },
  inputContainer: {
    gap: 16,
  },
  codeInput: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#6366f1',
    borderRadius: 16,
    padding: 20,
    fontSize: 32,
    fontWeight: 'bold',
    textAlign: 'center',
    letterSpacing: 8,
    color: '#1e293b',
  },
  joinButton: {
    backgroundColor: '#6366f1',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
  },
  joinButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '600',
  },
  disabledButton: {
    opacity: 0.5,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 14,
    textAlign: 'center',
  },
});
