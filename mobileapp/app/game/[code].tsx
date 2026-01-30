import { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  ScrollView,
  Alert,
  Share,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useGameStore } from '../../src/stores/gameStore';
import { useAuthStore } from '../../src/stores/authStore';

// Lobby Component
function LobbyView() {
  const { game, startGame, leaveGame, isLoading, error } = useGameStore();
  const player = useAuthStore((state) => state.player);
  const router = useRouter();

  if (!game) return null;

  const isHost = player?.id === game.host_player_id;
  const canStart = game.players.length >= (game.settings.min_players || 2);

  const handleShare = async () => {
    try {
      await Share.share({
        message: `Join my Select game! Code: ${game.code}`,
      });
    } catch (err) {
      console.error(err);
    }
  };

  const handleLeave = async () => {
    Alert.alert(
      'Leave Game',
      'Are you sure you want to leave?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Leave',
          style: 'destructive',
          onPress: async () => {
            await leaveGame();
            router.replace('/');
          },
        },
      ]
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.codeContainer}>
        <Text style={styles.codeLabel}>Game Code</Text>
        <Text style={styles.codeValue}>{game.code}</Text>
        <TouchableOpacity style={styles.shareButton} onPress={handleShare}>
          <Text style={styles.shareButtonText}>Share Code</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.playersContainer}>
        <Text style={styles.playersTitle}>
          Players ({game.players.length}/{game.settings.max_players || 8})
        </Text>
        {game.players.map((p) => (
          <View key={p.id} style={styles.playerItem}>
            <Text style={styles.playerName}>{p.display_name}</Text>
            {p.is_host && <Text style={styles.hostBadge}>Host</Text>}
            {p.id === player?.id && <Text style={styles.youBadge}>You</Text>}
          </View>
        ))}
      </View>

      {error && <Text style={styles.errorText}>{error}</Text>}

      <View style={styles.lobbyActions}>
        {isHost ? (
          <TouchableOpacity
            style={[
              styles.primaryButton,
              (!canStart || isLoading) && styles.disabledButton,
            ]}
            onPress={startGame}
            disabled={!canStart || isLoading}
          >
            {isLoading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>
                {canStart ? 'Start Game' : `Need ${game.settings.min_players || 2} players`}
              </Text>
            )}
          </TouchableOpacity>
        ) : (
          <Text style={styles.waitingText}>Waiting for host to start...</Text>
        )}

        <TouchableOpacity style={styles.leaveButton} onPress={handleLeave}>
          <Text style={styles.leaveButtonText}>Leave Game</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

// Play Component (Answer Phase)
function PlayView() {
  const { game, round, myAnswer, submitAnswer, answersCount, totalPlayers, isLoading, error } = useGameStore();
  const [answer, setAnswer] = useState('');
  const [timeLeft, setTimeLeft] = useState(0);

  useEffect(() => {
    if (round?.answer_deadline) {
      const deadline = new Date(round.answer_deadline).getTime();
      const interval = setInterval(() => {
        const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
        setTimeLeft(remaining);
      }, 1000);
      return () => clearInterval(interval);
    }
  }, [round?.answer_deadline]);

  if (!game || !round) return null;

  const handleSubmit = async () => {
    if (!answer.trim()) return;
    try {
      await submitAnswer(answer.trim());
      setAnswer('');
    } catch (err) {
      // Error handled by store
    }
  };

  // Validate answer matches acronym
  const words = answer.trim().split(/\s+/).filter(w => w);
  const acronymLetters = round.acronym.split('');
  const isValid = words.length === acronymLetters.length &&
    words.every((word, i) => word[0]?.toUpperCase() === acronymLetters[i]);

  return (
    <View style={styles.container}>
      <View style={styles.roundInfo}>
        <Text style={styles.roundNumber}>
          Round {round.round_number} of {game.total_rounds}
        </Text>
        <View style={styles.timerContainer}>
          <Text style={[styles.timer, timeLeft <= 10 && styles.timerWarning]}>
            {timeLeft}s
          </Text>
        </View>
      </View>

      <View style={styles.acronymContainer}>
        <Text style={styles.acronymLabel}>Create a sentence for:</Text>
        <Text style={styles.acronym}>{round.acronym}</Text>
        <Text style={styles.acronymHint}>
          Each word must start with the corresponding letter
        </Text>
      </View>

      {myAnswer ? (
        <View style={styles.submittedContainer}>
          <Text style={styles.submittedLabel}>Your answer:</Text>
          <Text style={styles.submittedAnswer}>{myAnswer.text}</Text>
          <Text style={styles.waitingText}>
            Waiting for others... ({answersCount}/{totalPlayers})
          </Text>
        </View>
      ) : (
        <View style={styles.answerContainer}>
          <TextInput
            style={styles.answerInput}
            value={answer}
            onChangeText={setAnswer}
            placeholder="Type your sentence..."
            multiline
            autoFocus
          />

          {answer.length > 0 && (
            <View style={styles.validationContainer}>
              {acronymLetters.map((letter, i) => {
                const word = words[i];
                const matches = word && word[0]?.toUpperCase() === letter;
                return (
                  <View
                    key={i}
                    style={[
                      styles.letterIndicator,
                      matches && styles.letterIndicatorValid,
                      word && !matches && styles.letterIndicatorInvalid,
                    ]}
                  >
                    <Text style={styles.letterIndicatorText}>{letter}</Text>
                  </View>
                );
              })}
            </View>
          )}

          {error && <Text style={styles.errorText}>{error}</Text>}

          <TouchableOpacity
            style={[
              styles.primaryButton,
              (!isValid || isLoading) && styles.disabledButton,
            ]}
            onPress={handleSubmit}
            disabled={!isValid || isLoading}
          >
            {isLoading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>Submit Answer</Text>
            )}
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
}

// Vote Component
function VoteView() {
  const { game, round, answers, myVote, submitVote, votesCount, totalPlayers, isLoading, error } = useGameStore();
  const player = useAuthStore((state) => state.player);
  const [timeLeft, setTimeLeft] = useState(0);

  useEffect(() => {
    if (round?.vote_deadline) {
      const deadline = new Date(round.vote_deadline).getTime();
      const interval = setInterval(() => {
        const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
        setTimeLeft(remaining);
      }, 1000);
      return () => clearInterval(interval);
    }
  }, [round?.vote_deadline]);

  if (!game || !round) return null;

  const handleVote = async (answerId: string) => {
    try {
      await submitVote(answerId);
    } catch (err) {
      // Error handled by store
    }
  };

  // Filter out own answer
  const votableAnswers = answers.filter((a) => a.player_id !== player?.id);

  return (
    <View style={styles.container}>
      <View style={styles.roundInfo}>
        <Text style={styles.roundNumber}>Vote for the best answer!</Text>
        <View style={styles.timerContainer}>
          <Text style={[styles.timer, timeLeft <= 10 && styles.timerWarning]}>
            {timeLeft}s
          </Text>
        </View>
      </View>

      <View style={styles.acronymContainer}>
        <Text style={styles.acronym}>{round.acronym}</Text>
      </View>

      <ScrollView style={styles.answersContainer}>
        {votableAnswers.length === 0 ? (
          <Text style={styles.noAnswersText}>
            No other answers to vote on. Waiting for results...
          </Text>
        ) : (
          votableAnswers.map((answer) => (
            <TouchableOpacity
              key={answer.id}
              style={[
                styles.answerCard,
                myVote === answer.id && styles.answerCardSelected,
              ]}
              onPress={() => handleVote(answer.id)}
              disabled={isLoading}
            >
              <Text style={styles.answerCardText}>{answer.text}</Text>
              <Text style={styles.answerCardAuthor}>- {answer.player_name}</Text>
            </TouchableOpacity>
          ))
        )}
      </ScrollView>

      {error && <Text style={styles.errorText}>{error}</Text>}

      <Text style={styles.voteProgress}>
        Votes: {votesCount}/{totalPlayers}
      </Text>
    </View>
  );
}

// Results Component
function ResultsView() {
  const { game, round, roundResults, finalScores } = useGameStore();
  const router = useRouter();

  if (!game || !round) return null;

  const isGameFinished = game.status === 'finished';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.resultsContent}>
      {isGameFinished ? (
        <>
          <Text style={styles.resultsTitle}>Game Over!</Text>
          <View style={styles.winnerContainer}>
            <Text style={styles.winnerLabel}>Winner</Text>
            <Text style={styles.winnerName}>{finalScores[0]?.player_name}</Text>
            <Text style={styles.winnerScore}>{finalScores[0]?.score} points</Text>
          </View>

          <View style={styles.leaderboard}>
            <Text style={styles.leaderboardTitle}>Final Standings</Text>
            {finalScores.map((score) => (
              <View key={score.player_id} style={styles.leaderboardItem}>
                <Text style={styles.leaderboardRank}>#{score.rank}</Text>
                <Text style={styles.leaderboardName}>{score.player_name}</Text>
                <Text style={styles.leaderboardScore}>{score.score}</Text>
              </View>
            ))}
          </View>

          <TouchableOpacity
            style={styles.primaryButton}
            onPress={() => router.replace('/')}
          >
            <Text style={styles.primaryButtonText}>Back to Home</Text>
          </TouchableOpacity>
        </>
      ) : (
        <>
          <Text style={styles.resultsTitle}>Round {round.round_number} Results</Text>
          <Text style={styles.acronym}>{round.acronym}</Text>

          <View style={styles.roundResultsContainer}>
            {roundResults.map((result, index) => (
              <View key={result.player_id} style={styles.resultCard}>
                <View style={styles.resultHeader}>
                  <Text style={styles.resultRank}>#{index + 1}</Text>
                  <Text style={styles.resultName}>{result.player_name}</Text>
                  <Text style={styles.resultVotes}>+{result.points_earned}</Text>
                </View>
                <Text style={styles.resultAnswer}>{result.answer}</Text>
                {result.voters.length > 0 && (
                  <Text style={styles.resultVoters}>
                    Voted by: {result.voters.map((v) => v.name).join(', ')}
                  </Text>
                )}
              </View>
            ))}
          </View>

          <Text style={styles.nextRoundText}>Next round starting soon...</Text>
        </>
      )}
    </ScrollView>
  );
}

// Main Game Screen
export default function GameScreen() {
  const { code } = useLocalSearchParams<{ code: string }>();
  const { game, joinGame, resetGame, isLoading, error } = useGameStore();
  const player = useAuthStore((state) => state.player);
  const router = useRouter();

  useEffect(() => {
    if (!player) {
      router.replace('/');
      return;
    }

    // If no game loaded, try to join
    if (!game && code) {
      joinGame(code);
    }

    return () => {
      // Clean up when leaving screen
    };
  }, [code, player]);

  if (isLoading && !game) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#6366f1" />
        <Text style={styles.loadingText}>Joining game...</Text>
      </View>
    );
  }

  if (error && !game) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorTitle}>Error</Text>
        <Text style={styles.errorText}>{error}</Text>
        <TouchableOpacity
          style={styles.primaryButton}
          onPress={() => router.replace('/')}
        >
          <Text style={styles.primaryButtonText}>Back to Home</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (!game) return null;

  // Render appropriate view based on game state
  if (game.status === 'lobby') {
    return <LobbyView />;
  }

  if (game.status === 'playing') {
    return <PlayView />;
  }

  if (game.status === 'voting') {
    return <VoteView />;
  }

  if (game.status === 'finished') {
    return <ResultsView />;
  }

  // Show results for completed round
  const { round, roundResults } = useGameStore.getState();
  if (round?.status === 'completed' && roundResults.length > 0) {
    return <ResultsView />;
  }

  return null;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
    padding: 24,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#64748b',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    padding: 24,
  },
  errorTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#ef4444',
    marginBottom: 8,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 24,
  },

  // Lobby styles
  codeContainer: {
    alignItems: 'center',
    marginBottom: 32,
  },
  codeLabel: {
    fontSize: 16,
    color: '#64748b',
    marginBottom: 8,
  },
  codeValue: {
    fontSize: 48,
    fontWeight: 'bold',
    color: '#6366f1',
    letterSpacing: 8,
  },
  shareButton: {
    marginTop: 12,
    paddingHorizontal: 20,
    paddingVertical: 8,
    backgroundColor: '#eef2ff',
    borderRadius: 8,
  },
  shareButtonText: {
    color: '#6366f1',
    fontWeight: '600',
  },
  playersContainer: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 24,
  },
  playersTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 16,
  },
  playerItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  playerName: {
    flex: 1,
    fontSize: 16,
    color: '#1e293b',
  },
  hostBadge: {
    backgroundColor: '#fef3c7',
    color: '#92400e',
    fontSize: 12,
    fontWeight: '600',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 8,
  },
  youBadge: {
    backgroundColor: '#dbeafe',
    color: '#1e40af',
    fontSize: 12,
    fontWeight: '600',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 8,
  },
  lobbyActions: {
    gap: 12,
    marginTop: 'auto',
  },
  waitingText: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 16,
    paddingVertical: 16,
  },
  leaveButton: {
    padding: 12,
    alignItems: 'center',
  },
  leaveButtonText: {
    color: '#ef4444',
    fontSize: 16,
    fontWeight: '500',
  },

  // Play styles
  roundInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  roundNumber: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1e293b',
  },
  timerContainer: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  timer: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#6366f1',
  },
  timerWarning: {
    color: '#ef4444',
  },
  acronymContainer: {
    alignItems: 'center',
    marginBottom: 32,
  },
  acronymLabel: {
    fontSize: 16,
    color: '#64748b',
    marginBottom: 8,
  },
  acronym: {
    fontSize: 56,
    fontWeight: 'bold',
    color: '#6366f1',
    letterSpacing: 12,
  },
  acronymHint: {
    fontSize: 14,
    color: '#94a3b8',
    marginTop: 8,
  },
  answerContainer: {
    gap: 16,
  },
  answerInput: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#e2e8f0',
    borderRadius: 16,
    padding: 16,
    fontSize: 18,
    minHeight: 100,
    textAlignVertical: 'top',
  },
  validationContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  letterIndicator: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#e2e8f0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  letterIndicatorValid: {
    backgroundColor: '#dcfce7',
  },
  letterIndicatorInvalid: {
    backgroundColor: '#fee2e2',
  },
  letterIndicatorText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1e293b',
  },
  submittedContainer: {
    alignItems: 'center',
    gap: 12,
  },
  submittedLabel: {
    fontSize: 16,
    color: '#64748b',
  },
  submittedAnswer: {
    fontSize: 20,
    fontWeight: '600',
    color: '#1e293b',
    textAlign: 'center',
    backgroundColor: '#dcfce7',
    padding: 16,
    borderRadius: 12,
  },

  // Vote styles
  answersContainer: {
    flex: 1,
    marginBottom: 16,
  },
  noAnswersText: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 16,
    paddingVertical: 32,
  },
  answerCard: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#e2e8f0',
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
  },
  answerCardSelected: {
    borderColor: '#6366f1',
    backgroundColor: '#eef2ff',
  },
  answerCardText: {
    fontSize: 18,
    color: '#1e293b',
    marginBottom: 8,
  },
  answerCardAuthor: {
    fontSize: 14,
    color: '#64748b',
    fontStyle: 'italic',
  },
  voteProgress: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 14,
  },

  // Results styles
  resultsContent: {
    paddingBottom: 48,
  },
  resultsTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1e293b',
    textAlign: 'center',
    marginBottom: 16,
  },
  winnerContainer: {
    alignItems: 'center',
    backgroundColor: '#fef3c7',
    borderRadius: 16,
    padding: 24,
    marginBottom: 24,
  },
  winnerLabel: {
    fontSize: 14,
    color: '#92400e',
    marginBottom: 4,
  },
  winnerName: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#92400e',
  },
  winnerScore: {
    fontSize: 18,
    color: '#92400e',
    marginTop: 4,
  },
  leaderboard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 24,
  },
  leaderboardTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 16,
  },
  leaderboardItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  leaderboardRank: {
    width: 40,
    fontSize: 16,
    fontWeight: 'bold',
    color: '#64748b',
  },
  leaderboardName: {
    flex: 1,
    fontSize: 16,
    color: '#1e293b',
  },
  leaderboardScore: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#6366f1',
  },
  roundResultsContainer: {
    gap: 12,
    marginTop: 16,
  },
  resultCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
  },
  resultHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  resultRank: {
    width: 32,
    fontSize: 16,
    fontWeight: 'bold',
    color: '#6366f1',
  },
  resultName: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
  },
  resultVotes: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#22c55e',
  },
  resultAnswer: {
    fontSize: 16,
    color: '#1e293b',
    marginBottom: 4,
  },
  resultVoters: {
    fontSize: 12,
    color: '#94a3b8',
  },
  nextRoundText: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 16,
    marginTop: 24,
  },

  // Common
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
  disabledButton: {
    opacity: 0.5,
  },
});
