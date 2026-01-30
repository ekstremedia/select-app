import { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useAuthStore } from '../src/stores/authStore';

export default function HomeScreen() {
  const router = useRouter();
  const { player, isLoading, error, createGuest, clearError } = useAuthStore();
  const [displayName, setDisplayName] = useState('');

  const handleCreateGuest = async () => {
    if (displayName.trim().length < 2) {
      Alert.alert('Error', 'Name must be at least 2 characters');
      return;
    }

    try {
      await createGuest(displayName.trim());
    } catch (err) {
      // Error is handled by the store
    }
  };

  // If user is already authenticated, show main menu
  if (player) {
    return (
      <View style={styles.container}>
        <Text style={styles.title}>Gullkorn</Text>
        <Text style={styles.subtitle}>Welcome, {player.display_name}!</Text>

        <View style={styles.buttonContainer}>
          <TouchableOpacity
            style={styles.primaryButton}
            onPress={() => router.push('/create')}
          >
            <Text style={styles.primaryButtonText}>Create Game</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryButton}
            onPress={() => router.push('/join')}
          >
            <Text style={styles.secondaryButtonText}>Join Game</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.tertiaryButton}
            onPress={() => router.push('/profile')}
          >
            <Text style={styles.tertiaryButtonText}>Profile</Text>
          </TouchableOpacity>
        </View>

        {player.is_guest && (
          <Text style={styles.guestHint}>
            Playing as guest. Create an account to save your stats!
          </Text>
        )}
      </View>
    );
  }

  // Guest creation screen
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Gullkorn</Text>
      <Text style={styles.subtitle}>Create acronym sentences with friends!</Text>

      <View style={styles.inputContainer}>
        <Text style={styles.label}>Choose your display name</Text>
        <TextInput
          style={styles.input}
          value={displayName}
          onChangeText={setDisplayName}
          placeholder="Enter your name"
          maxLength={50}
          autoCapitalize="words"
          autoCorrect={false}
        />

        {error && (
          <Text style={styles.errorText}>{error}</Text>
        )}

        <TouchableOpacity
          style={[styles.primaryButton, isLoading && styles.disabledButton]}
          onPress={handleCreateGuest}
          disabled={isLoading}
        >
          {isLoading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryButtonText}>Play as Guest</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => router.push('/profile')}
        >
          <Text style={styles.linkButtonText}>Already have an account? Sign in</Text>
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
    fontSize: 48,
    fontWeight: 'bold',
    color: '#6366f1',
    textAlign: 'center',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 18,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 48,
  },
  inputContainer: {
    gap: 16,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 4,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 12,
    padding: 16,
    fontSize: 16,
  },
  buttonContainer: {
    gap: 12,
  },
  primaryButton: {
    backgroundColor: '#6366f1',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '600',
  },
  secondaryButton: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#6366f1',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
  },
  secondaryButtonText: {
    color: '#6366f1',
    fontSize: 18,
    fontWeight: '600',
  },
  tertiaryButton: {
    padding: 16,
    alignItems: 'center',
  },
  tertiaryButtonText: {
    color: '#64748b',
    fontSize: 16,
    fontWeight: '500',
  },
  linkButton: {
    padding: 12,
    alignItems: 'center',
  },
  linkButtonText: {
    color: '#6366f1',
    fontSize: 14,
  },
  disabledButton: {
    opacity: 0.7,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 14,
    textAlign: 'center',
  },
  guestHint: {
    marginTop: 24,
    color: '#64748b',
    fontSize: 14,
    textAlign: 'center',
  },
});
