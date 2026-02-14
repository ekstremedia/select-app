import { create } from 'zustand';
import {
  Game,
  Round,
  Answer,
  GamePlayer,
  RoundResult,
  FinalScore,
  PlayerJoinedEvent,
  PlayerLeftEvent,
  GameStartedEvent,
  RoundStartedEvent,
  AnswerSubmittedEvent,
  VotingStartedEvent,
  VoteSubmittedEvent,
  RoundCompletedEvent,
  GameFinishedEvent,
} from '../types';
import { api } from '../services/api';
import { websocket, GameChannelCallbacks } from '../services/websocket';

interface GameState {
  // Game state
  game: Game | null;
  round: Round | null;
  answers: Answer[];
  myAnswer: Answer | null;
  myVote: string | null; // answer_id
  roundResults: RoundResult[];
  finalScores: FinalScore[];

  // Progress tracking
  answersCount: number;
  totalPlayers: number;
  votesCount: number;

  // UI state
  isLoading: boolean;
  error: string | null;
  timeRemaining: number;

  // Actions
  createGame: (settings?: Partial<Game['settings']>) => Promise<void>;
  joinGame: (code: string) => Promise<void>;
  leaveGame: () => Promise<void>;
  startGame: () => Promise<void>;
  submitAnswer: (text: string) => Promise<void>;
  submitVote: (answerId: string) => Promise<void>;
  refreshRound: () => Promise<void>;
  resetGame: () => void;
  setTimeRemaining: (time: number) => void;
  clearError: () => void;

  // WebSocket event handlers
  handlePlayerJoined: (event: PlayerJoinedEvent) => void;
  handlePlayerLeft: (event: PlayerLeftEvent) => void;
  handleGameStarted: (event: GameStartedEvent) => void;
  handleRoundStarted: (event: RoundStartedEvent) => void;
  handleAnswerSubmitted: (event: AnswerSubmittedEvent) => void;
  handleVotingStarted: (event: VotingStartedEvent) => void;
  handleVoteSubmitted: (event: VoteSubmittedEvent) => void;
  handleRoundCompleted: (event: RoundCompletedEvent) => void;
  handleGameFinished: (event: GameFinishedEvent) => void;
}

export const useGameStore = create<GameState>((set, get) => ({
  game: null,
  round: null,
  answers: [],
  myAnswer: null,
  myVote: null,
  roundResults: [],
  finalScores: [],
  answersCount: 0,
  totalPlayers: 0,
  votesCount: 0,
  isLoading: false,
  error: null,
  timeRemaining: 0,

  createGame: async (settings) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.createGame(settings);
      const { game } = response;

      // Join WebSocket channel
      const callbacks = get().getWebSocketCallbacks();
      websocket.joinGameChannel(game.code, callbacks);

      set({
        game,
        isLoading: false,
        totalPlayers: game.players.length,
      });
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to create game',
      });
      throw error;
    }
  },

  joinGame: async (code: string) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.joinGame(code.toUpperCase());
      const { game } = response;

      // Join WebSocket channel
      const callbacks = get().getWebSocketCallbacks();
      websocket.joinGameChannel(game.code, callbacks);

      set({
        game,
        isLoading: false,
        totalPlayers: game.players.length,
      });
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to join game',
      });
      throw error;
    }
  },

  leaveGame: async () => {
    const { game } = get();
    if (!game) return;

    set({ isLoading: true, error: null });
    try {
      await api.leaveGame(game.code);
      websocket.leaveGameChannel(game.code);
      get().resetGame();
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to leave game',
      });
      throw error;
    }
  },

  startGame: async () => {
    const { game } = get();
    if (!game) return;

    set({ isLoading: true, error: null });
    try {
      const response = await api.startGame(game.code);
      set({
        game: response.game,
        round: response.round,
        isLoading: false,
        answersCount: 0,
        myAnswer: null,
        myVote: null,
      });
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to start game',
      });
      throw error;
    }
  },

  submitAnswer: async (text: string) => {
    const { round } = get();
    if (!round) return;

    set({ isLoading: true, error: null });
    try {
      const response = await api.submitAnswer(round.id, text);
      set({
        myAnswer: response.answer,
        isLoading: false,
      });
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to submit answer',
      });
      throw error;
    }
  },

  submitVote: async (answerId: string) => {
    const { round } = get();
    if (!round) return;

    set({ isLoading: true, error: null });
    try {
      await api.submitVote(round.id, answerId);
      set({
        myVote: answerId,
        isLoading: false,
      });
    } catch (error) {
      set({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Failed to submit vote',
      });
      throw error;
    }
  },

  refreshRound: async () => {
    const { game } = get();
    if (!game) return;

    try {
      const response = await api.getCurrentRound(game.code);
      set({
        round: response.round,
        answers: response.answers || [],
      });
    } catch (error) {
      console.error('Failed to refresh round:', error);
    }
  },

  resetGame: () => {
    set({
      game: null,
      round: null,
      answers: [],
      myAnswer: null,
      myVote: null,
      roundResults: [],
      finalScores: [],
      answersCount: 0,
      totalPlayers: 0,
      votesCount: 0,
      isLoading: false,
      error: null,
      timeRemaining: 0,
    });
  },

  setTimeRemaining: (time: number) => {
    set({ timeRemaining: time });
  },

  clearError: () => {
    set({ error: null });
  },

  // WebSocket event handlers
  handlePlayerJoined: (event: PlayerJoinedEvent) => {
    const { game } = get();
    if (!game) return;

    const newPlayer: GamePlayer = {
      id: event.player.id,
      nickname: event.player.nickname,
      score: 0,
      is_host: false,
    };

    set({
      game: {
        ...game,
        players: [...game.players, newPlayer],
      },
      totalPlayers: event.players_count,
    });
  },

  handlePlayerLeft: (event: PlayerLeftEvent) => {
    const { game } = get();
    if (!game) return;

    const updatedPlayers = game.players
      .filter((p) => p.id !== event.player_id)
      .map((p) => ({
        ...p,
        is_host: p.id === event.new_host_id,
      }));

    set({
      game: {
        ...game,
        players: updatedPlayers,
        host_player_id: event.new_host_id,
      },
      totalPlayers: event.players_count,
    });
  },

  handleGameStarted: (event: GameStartedEvent) => {
    const { game } = get();
    if (!game) return;

    set({
      game: {
        ...game,
        status: 'playing',
        total_rounds: event.total_rounds,
      },
    });
  },

  handleRoundStarted: (event: RoundStartedEvent) => {
    const { game } = get();
    if (!game) return;

    set({
      game: {
        ...game,
        status: 'playing',
        current_round: event.round_number,
      },
      round: {
        id: event.round_id,
        round_number: event.round_number,
        acronym: event.acronym,
        status: 'answering',
        answer_deadline: event.deadline,
      },
      answers: [],
      myAnswer: null,
      myVote: null,
      answersCount: 0,
      roundResults: [],
    });
  },

  handleAnswerSubmitted: (event: AnswerSubmittedEvent) => {
    set({
      answersCount: event.answers_count,
      totalPlayers: event.total_players,
    });
  },

  handleVotingStarted: (event: VotingStartedEvent) => {
    const { game, round } = get();
    if (!game || !round) return;

    set({
      game: {
        ...game,
        status: 'voting',
      },
      round: {
        ...round,
        status: 'voting',
        vote_deadline: event.deadline,
      },
      answers: event.answers,
      votesCount: 0,
    });
  },

  handleVoteSubmitted: (event: VoteSubmittedEvent) => {
    set({
      votesCount: event.votes_count,
      totalPlayers: event.total_voters,
    });
  },

  handleRoundCompleted: (event: RoundCompletedEvent) => {
    const { game, round } = get();
    if (!game || !round) return;

    // Update player scores
    const updatedPlayers = game.players.map((p) => {
      const scoreData = event.scores.find((s) => s.player_id === p.id);
      return scoreData ? { ...p, score: scoreData.score } : p;
    });

    set({
      game: {
        ...game,
        players: updatedPlayers,
      },
      round: {
        ...round,
        status: 'completed',
      },
      roundResults: event.results,
    });
  },

  handleGameFinished: (event: GameFinishedEvent) => {
    const { game } = get();
    if (!game) return;

    set({
      game: {
        ...game,
        status: 'finished',
      },
      finalScores: event.final_scores,
    });
  },

  // Helper to get callbacks for WebSocket
  getWebSocketCallbacks: (): GameChannelCallbacks => ({
    onPlayerJoined: get().handlePlayerJoined,
    onPlayerLeft: get().handlePlayerLeft,
    onGameStarted: get().handleGameStarted,
    onRoundStarted: get().handleRoundStarted,
    onAnswerSubmitted: get().handleAnswerSubmitted,
    onVotingStarted: get().handleVotingStarted,
    onVoteSubmitted: get().handleVoteSubmitted,
    onRoundCompleted: get().handleRoundCompleted,
    onGameFinished: get().handleGameFinished,
  }),
}));

// Extend the store with the helper method
declare module 'zustand' {
  interface GameState {
    getWebSocketCallbacks: () => GameChannelCallbacks;
  }
}
