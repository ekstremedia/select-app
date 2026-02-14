import { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  ScrollView,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useAuthStore } from '../src/stores/authStore';

export default function ProfileScreen() {
  const router = useRouter();
  const {
    player,
    user,
    isLoading,
    error,
    login,
    register,
    convertGuest,
    logout,
    clearError,
  } = useAuthStore();

  const [mode, setMode] = useState<'view' | 'login' | 'register'>('view');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please fill in all fields');
      return;
    }

    try {
      await login(email, password);
      setMode('view');
      setEmail('');
      setPassword('');
    } catch (err) {
      // Error handled by store
    }
  };

  const handleRegister = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please fill in all fields');
      return;
    }

    try {
      if (player?.is_guest) {
        await convertGuest(email, password, name || undefined);
      } else {
        await register(email, password, name || undefined);
      }
      setMode('view');
      setEmail('');
      setPassword('');
      setName('');
    } catch (err) {
      // Error handled by store
    }
  };

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: () => {
            logout();
            router.replace('/');
          },
        },
      ]
    );
  };

  // Login form
  if (mode === 'login') {
    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.title}>Sign In</Text>

        <View style={styles.form}>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            placeholder="Email"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
          />

          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            placeholder="Password"
            secureTextEntry
          />

          {error && <Text style={styles.errorText}>{error}</Text>}

          <TouchableOpacity
            style={[styles.primaryButton, isLoading && styles.disabledButton]}
            onPress={handleLogin}
            disabled={isLoading}
          >
            {isLoading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>Sign In</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.linkButton}
            onPress={() => {
              setMode('register');
              clearError();
            }}
          >
            <Text style={styles.linkButtonText}>Don't have an account? Register</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.linkButton}
            onPress={() => {
              setMode('view');
              clearError();
            }}
          >
            <Text style={styles.linkButtonText}>Back</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    );
  }

  // Register form
  if (mode === 'register') {
    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.title}>
          {player?.is_guest ? 'Create Account' : 'Register'}
        </Text>
        {player?.is_guest && (
          <Text style={styles.subtitle}>
            Your game history will be preserved!
          </Text>
        )}

        <View style={styles.form}>
          <TextInput
            style={styles.input}
            value={name}
            onChangeText={setName}
            placeholder="Display Name (optional)"
            autoCapitalize="words"
          />

          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            placeholder="Email"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
          />

          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            placeholder="Password (min 8 characters)"
            secureTextEntry
          />

          {error && <Text style={styles.errorText}>{error}</Text>}

          <TouchableOpacity
            style={[styles.primaryButton, isLoading && styles.disabledButton]}
            onPress={handleRegister}
            disabled={isLoading}
          >
            {isLoading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>Create Account</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.linkButton}
            onPress={() => {
              setMode('login');
              clearError();
            }}
          >
            <Text style={styles.linkButtonText}>Already have an account? Sign in</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.linkButton}
            onPress={() => {
              setMode('view');
              clearError();
            }}
          >
            <Text style={styles.linkButtonText}>Back</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    );
  }

  // Profile view
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {player ? (
        <>
          <View style={styles.profileHeader}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>
                {player.nickname.charAt(0).toUpperCase()}
              </Text>
            </View>
            <Text style={styles.displayName}>{player.nickname}</Text>
            {user && <Text style={styles.email}>{user.email}</Text>}
            {player.is_guest && (
              <View style={styles.guestBadge}>
                <Text style={styles.guestBadgeText}>Guest</Text>
              </View>
            )}
          </View>

          <View style={styles.statsContainer}>
            <Text style={styles.statsTitle}>Statistics</Text>
            <View style={styles.statsRow}>
              <View style={styles.statItem}>
                <Text style={styles.statValue}>
                  {player.stats?.games_played ?? 0}
                </Text>
                <Text style={styles.statLabel}>Games</Text>
              </View>
              <View style={styles.statItem}>
                <Text style={styles.statValue}>
                  {player.stats?.games_won ?? 0}
                </Text>
                <Text style={styles.statLabel}>Wins</Text>
              </View>
              <View style={styles.statItem}>
                <Text style={styles.statValue}>
                  {player.stats?.total_score ?? 0}
                </Text>
                <Text style={styles.statLabel}>Points</Text>
              </View>
            </View>
          </View>

          {player.is_guest && (
            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => setMode('register')}
            >
              <Text style={styles.primaryButtonText}>Create Account</Text>
            </TouchableOpacity>
          )}

          <TouchableOpacity
            style={styles.logoutButton}
            onPress={handleLogout}
          >
            <Text style={styles.logoutButtonText}>
              {player.is_guest ? 'Start Over' : 'Logout'}
            </Text>
          </TouchableOpacity>
        </>
      ) : (
        <View style={styles.noPlayerContainer}>
          <Text style={styles.noPlayerText}>
            Sign in or create an account to track your stats
          </Text>

          <TouchableOpacity
            style={styles.primaryButton}
            onPress={() => setMode('login')}
          >
            <Text style={styles.primaryButtonText}>Sign In</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryButton}
            onPress={() => setMode('register')}
          >
            <Text style={styles.secondaryButtonText}>Create Account</Text>
          </TouchableOpacity>
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  content: {
    padding: 24,
    gap: 24,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1e293b',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
  },
  form: {
    gap: 16,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 12,
    padding: 16,
    fontSize: 16,
  },
  profileHeader: {
    alignItems: 'center',
    gap: 12,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#6366f1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 36,
    fontWeight: 'bold',
    color: '#fff',
  },
  displayName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1e293b',
  },
  email: {
    fontSize: 16,
    color: '#64748b',
  },
  guestBadge: {
    backgroundColor: '#fef3c7',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  guestBadgeText: {
    color: '#92400e',
    fontSize: 14,
    fontWeight: '500',
  },
  statsContainer: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
  },
  statsTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 16,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  statItem: {
    alignItems: 'center',
  },
  statValue: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#6366f1',
  },
  statLabel: {
    fontSize: 14,
    color: '#64748b',
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
  logoutButton: {
    padding: 16,
    alignItems: 'center',
  },
  logoutButtonText: {
    color: '#ef4444',
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
  noPlayerContainer: {
    gap: 16,
    marginTop: 48,
  },
  noPlayerText: {
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 16,
  },
});
