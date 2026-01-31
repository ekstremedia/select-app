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
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../src/stores/authStore';
import { useTheme } from '../src/theme';
import ConnectionStatus from '../src/components/ConnectionStatus';

export default function HomeScreen() {
  const router = useRouter();
  const { t } = useTranslation();
  const { theme } = useTheme();
  const { player, isLoading, error, createGuest } = useAuthStore();
  const [displayName, setDisplayName] = useState('');

  const handleCreateGuest = async () => {
    if (displayName.trim().length < 2) {
      Alert.alert(t('common.error'), t('auth.nameTooShort'));
      return;
    }

    try {
      await createGuest(displayName.trim());
    } catch (err) {
      // Error is handled by the store
    }
  };

  const styles = createStyles(theme);

  if (player) {
    return (
      <View style={styles.container}>
        <ConnectionStatus />
        <Text style={styles.title}>Select</Text>
        <Text style={styles.subtitle}>{t('home.welcome', { name: player.display_name })}</Text>

        <View style={styles.buttonContainer}>
          <TouchableOpacity
            style={styles.primaryButton}
            onPress={() => router.push('/create')}
          >
            <Text style={styles.primaryButtonText}>{t('home.createGame')}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryButton}
            onPress={() => router.push('/join')}
          >
            <Text style={styles.secondaryButtonText}>{t('home.joinGame')}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.tertiaryButton}
            onPress={() => router.push('/settings')}
          >
            <Text style={styles.tertiaryButtonText}>{t('settings.title')}</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ConnectionStatus />
      <Text style={styles.title}>Select</Text>
      <Text style={styles.subtitle}>{t('home.tagline')}</Text>

      <View style={styles.inputContainer}>
        <Text style={styles.label}>{t('auth.chooseDisplayName')}</Text>
        <TextInput
          style={styles.input}
          value={displayName}
          onChangeText={setDisplayName}
          placeholder={t('auth.enterName')}
          placeholderTextColor={theme.inputPlaceholder}
          maxLength={50}
          autoCapitalize="words"
          autoCorrect={false}
        />

        {error && <Text style={styles.errorText}>{error}</Text>}

        <TouchableOpacity
          style={[styles.primaryButton, isLoading && styles.disabledButton]}
          onPress={handleCreateGuest}
          disabled={isLoading}
        >
          {isLoading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryButtonText}>{t('auth.playAsGuest')}</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => router.push('/settings')}
        >
          <Text style={styles.linkButtonText}>{t('settings.title')}</Text>
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
      fontSize: 48,
      fontWeight: 'bold',
      color: theme.primary,
      textAlign: 'center',
      marginBottom: 8,
    },
    subtitle: {
      fontSize: 18,
      color: theme.textSecondary,
      textAlign: 'center',
      marginBottom: 48,
    },
    inputContainer: {
      gap: 16,
    },
    label: {
      fontSize: 16,
      fontWeight: '600',
      color: theme.text,
      marginBottom: 4,
    },
    input: {
      backgroundColor: theme.inputBackground,
      borderWidth: 1,
      borderColor: theme.inputBorder,
      borderRadius: 12,
      padding: 16,
      fontSize: 16,
      color: theme.inputText,
    },
    buttonContainer: {
      gap: 12,
    },
    primaryButton: {
      backgroundColor: theme.buttonPrimaryBackground,
      borderRadius: 12,
      padding: 16,
      alignItems: 'center',
    },
    primaryButtonText: {
      color: theme.buttonPrimaryText,
      fontSize: 18,
      fontWeight: '600',
    },
    secondaryButton: {
      backgroundColor: theme.buttonSecondaryBackground,
      borderWidth: 2,
      borderColor: theme.buttonSecondaryBorder,
      borderRadius: 12,
      padding: 16,
      alignItems: 'center',
    },
    secondaryButtonText: {
      color: theme.buttonSecondaryText,
      fontSize: 18,
      fontWeight: '600',
    },
    tertiaryButton: {
      padding: 16,
      alignItems: 'center',
    },
    tertiaryButtonText: {
      color: theme.textSecondary,
      fontSize: 16,
      fontWeight: '500',
    },
    linkButton: {
      padding: 12,
      alignItems: 'center',
    },
    linkButtonText: {
      color: theme.primary,
      fontSize: 14,
    },
    disabledButton: {
      opacity: 0.7,
    },
    errorText: {
      color: theme.error,
      fontSize: 14,
      textAlign: 'center',
    },
  });
