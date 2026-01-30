import { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  ScrollView,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useGameStore } from '../src/stores/gameStore';
import { useAuthStore } from '../src/stores/authStore';

export default function CreateGameScreen() {
  const router = useRouter();
  const { createGame, isLoading, error, clearError } = useGameStore();
  const player = useAuthStore((state) => state.player);

  const [rounds, setRounds] = useState(5);
  const [answerTime, setAnswerTime] = useState(60);
  const [voteTime, setVoteTime] = useState(30);

  const handleCreate = async () => {
    try {
      await createGame({
        rounds,
        answer_time: answerTime,
        vote_time: voteTime,
      });

      const game = useGameStore.getState().game;
      if (game) {
        router.replace(`/game/${game.code}`);
      }
    } catch (err) {
      // Error handled by store
    }
  };

  if (!player) {
    router.replace('/');
    return null;
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Game Settings</Text>

      <View style={styles.setting}>
        <Text style={styles.settingLabel}>Number of Rounds</Text>
        <View style={styles.settingRow}>
          {[3, 5, 7, 10].map((value) => (
            <TouchableOpacity
              key={value}
              style={[
                styles.optionButton,
                rounds === value && styles.optionButtonActive,
              ]}
              onPress={() => setRounds(value)}
            >
              <Text
                style={[
                  styles.optionButtonText,
                  rounds === value && styles.optionButtonTextActive,
                ]}
              >
                {value}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      <View style={styles.setting}>
        <Text style={styles.settingLabel}>Answer Time (seconds)</Text>
        <View style={styles.settingRow}>
          {[30, 45, 60, 90].map((value) => (
            <TouchableOpacity
              key={value}
              style={[
                styles.optionButton,
                answerTime === value && styles.optionButtonActive,
              ]}
              onPress={() => setAnswerTime(value)}
            >
              <Text
                style={[
                  styles.optionButtonText,
                  answerTime === value && styles.optionButtonTextActive,
                ]}
              >
                {value}s
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      <View style={styles.setting}>
        <Text style={styles.settingLabel}>Vote Time (seconds)</Text>
        <View style={styles.settingRow}>
          {[15, 20, 30, 45].map((value) => (
            <TouchableOpacity
              key={value}
              style={[
                styles.optionButton,
                voteTime === value && styles.optionButtonActive,
              ]}
              onPress={() => setVoteTime(value)}
            >
              <Text
                style={[
                  styles.optionButtonText,
                  voteTime === value && styles.optionButtonTextActive,
                ]}
              >
                {value}s
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {error && <Text style={styles.errorText}>{error}</Text>}

      <TouchableOpacity
        style={[styles.createButton, isLoading && styles.disabledButton]}
        onPress={handleCreate}
        disabled={isLoading}
      >
        {isLoading ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.createButtonText}>Create Game</Text>
        )}
      </TouchableOpacity>
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
    marginBottom: 8,
  },
  setting: {
    gap: 12,
  },
  settingLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
  },
  settingRow: {
    flexDirection: 'row',
    gap: 8,
  },
  optionButton: {
    flex: 1,
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#e2e8f0',
    borderRadius: 12,
    padding: 12,
    alignItems: 'center',
  },
  optionButtonActive: {
    borderColor: '#6366f1',
    backgroundColor: '#eef2ff',
  },
  optionButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#64748b',
  },
  optionButtonTextActive: {
    color: '#6366f1',
  },
  createButton: {
    backgroundColor: '#6366f1',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
    marginTop: 16,
  },
  createButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '600',
  },
  disabledButton: {
    opacity: 0.7,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 14,
    textAlign: 'center',
  },
});
