export interface Player {
  id: string;
  nickname: string;
  guest_token?: string;
  is_guest: boolean;
  stats?: PlayerStats;
}

export interface PlayerStats {
  games_played: number;
  games_won: number;
  total_score: number;
}

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface GameSettings {
  min_players: number;
  max_players: number;
  rounds: number;
  answer_time: number;
  vote_time: number;
  acronym_length_min: number;
  acronym_length_max: number;
}

export interface GamePlayer {
  id: string;
  nickname: string;
  score: number;
  is_host: boolean;
}

export interface Game {
  id: string;
  code: string;
  status: 'lobby' | 'playing' | 'voting' | 'finished';
  host_player_id: string;
  current_round: number;
  total_rounds: number;
  settings: GameSettings;
  players: GamePlayer[];
}

export interface Round {
  id: string;
  round_number: number;
  acronym: string;
  status: 'answering' | 'voting' | 'completed';
  answer_deadline?: string;
  vote_deadline?: string;
}

export interface Answer {
  id: string;
  player_id: string;
  player_name: string;
  text: string;
  votes_count?: number;
}

export interface Vote {
  id: string;
  answer_id: string;
}

export interface RoundResult {
  player_id: string;
  player_name: string;
  answer: string;
  votes: number;
  points_earned: number;
  voters: Array<{ id: string; name: string }>;
}

export interface FinalScore {
  rank: number;
  player_id: string;
  player_name: string;
  score: number;
  is_winner: boolean;
}

// API Response types
export interface AuthResponse {
  player: Player;
  user?: User;
  token?: string;
}

export interface GameResponse {
  game: Game;
}

export interface RoundResponse {
  round: Round;
  answers?: Answer[];
}

// WebSocket event types
export interface PlayerJoinedEvent {
  player: {
    id: string;
    nickname: string;
  };
  players_count: number;
}

export interface PlayerLeftEvent {
  player_id: string;
  players_count: number;
  new_host_id: string;
}

export interface GameStartedEvent {
  game_id: string;
  total_rounds: number;
}

export interface RoundStartedEvent {
  round_id: string;
  round_number: number;
  acronym: string;
  deadline: string;
}

export interface AnswerSubmittedEvent {
  answers_count: number;
  total_players: number;
}

export interface VotingStartedEvent {
  round_id: string;
  answers: Answer[];
  deadline: string;
}

export interface VoteSubmittedEvent {
  votes_count: number;
  total_voters: number;
}

export interface RoundCompletedEvent {
  results: RoundResult[];
  scores: Array<{
    player_id: string;
    player_name: string;
    score: number;
  }>;
}

export interface GameFinishedEvent {
  winner: FinalScore;
  final_scores: FinalScore[];
}
