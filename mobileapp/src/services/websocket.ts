import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
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

// Make Pusher available globally for Laravel Echo
(global as any).Pusher = Pusher;

const REVERB_HOST = process.env.EXPO_PUBLIC_REVERB_HOST || 'localhost';
const REVERB_PORT = process.env.EXPO_PUBLIC_REVERB_PORT || '8080';
const REVERB_SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME || 'http';
const REVERB_APP_KEY = process.env.EXPO_PUBLIC_REVERB_APP_KEY || 'app-key';

export interface GameChannelCallbacks {
  onPlayerJoined?: (event: PlayerJoinedEvent) => void;
  onPlayerLeft?: (event: PlayerLeftEvent) => void;
  onGameStarted?: (event: GameStartedEvent) => void;
  onRoundStarted?: (event: RoundStartedEvent) => void;
  onAnswerSubmitted?: (event: AnswerSubmittedEvent) => void;
  onVotingStarted?: (event: VotingStartedEvent) => void;
  onVoteSubmitted?: (event: VoteSubmittedEvent) => void;
  onRoundCompleted?: (event: RoundCompletedEvent) => void;
  onGameFinished?: (event: GameFinishedEvent) => void;
}

class WebSocketService {
  private echo: Echo<'pusher'> | null = null;
  private currentChannel: string | null = null;

  initialize(guestToken?: string, authToken?: string) {
    if (this.echo) {
      this.disconnect();
    }

    const authHeaders: Record<string, string> = {};
    if (authToken) {
      authHeaders['Authorization'] = `Bearer ${authToken}`;
    }
    if (guestToken) {
      authHeaders['X-Guest-Token'] = guestToken;
    }

    this.echo = new Echo({
      broadcaster: 'pusher',
      key: REVERB_APP_KEY,
      wsHost: REVERB_HOST,
      wsPort: parseInt(REVERB_PORT, 10),
      wssPort: parseInt(REVERB_PORT, 10),
      forceTLS: REVERB_SCHEME === 'https',
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      cluster: 'mt1',
      authEndpoint: `${REVERB_SCHEME}://${REVERB_HOST}:${REVERB_PORT}/broadcasting/auth`,
      auth: {
        headers: authHeaders,
      },
    });

    return this.echo;
  }

  joinGameChannel(gameCode: string, callbacks: GameChannelCallbacks) {
    if (!this.echo) {
      throw new Error('WebSocket not initialized');
    }

    // Leave previous channel if any
    if (this.currentChannel) {
      this.leaveGameChannel(this.currentChannel);
    }

    this.currentChannel = gameCode;

    const channel = this.echo.join(`game.${gameCode}`);

    // Set up event listeners
    if (callbacks.onPlayerJoined) {
      channel.listen('.player.joined', callbacks.onPlayerJoined);
    }
    if (callbacks.onPlayerLeft) {
      channel.listen('.player.left', callbacks.onPlayerLeft);
    }
    if (callbacks.onGameStarted) {
      channel.listen('.game.started', callbacks.onGameStarted);
    }
    if (callbacks.onRoundStarted) {
      channel.listen('.round.started', callbacks.onRoundStarted);
    }
    if (callbacks.onAnswerSubmitted) {
      channel.listen('.answer.submitted', callbacks.onAnswerSubmitted);
    }
    if (callbacks.onVotingStarted) {
      channel.listen('.voting.started', callbacks.onVotingStarted);
    }
    if (callbacks.onVoteSubmitted) {
      channel.listen('.vote.submitted', callbacks.onVoteSubmitted);
    }
    if (callbacks.onRoundCompleted) {
      channel.listen('.round.completed', callbacks.onRoundCompleted);
    }
    if (callbacks.onGameFinished) {
      channel.listen('.game.finished', callbacks.onGameFinished);
    }

    // Listen for presence events
    channel
      .here((users: any[]) => {
        console.log('Users in channel:', users);
      })
      .joining((user: any) => {
        console.log('User joining:', user);
      })
      .leaving((user: any) => {
        console.log('User leaving:', user);
      })
      .error((error: any) => {
        console.error('Channel error:', error);
      });

    return channel;
  }

  leaveGameChannel(gameCode: string) {
    if (this.echo) {
      this.echo.leave(`game.${gameCode}`);
      if (this.currentChannel === gameCode) {
        this.currentChannel = null;
      }
    }
  }

  disconnect() {
    if (this.echo) {
      if (this.currentChannel) {
        this.leaveGameChannel(this.currentChannel);
      }
      this.echo.disconnect();
      this.echo = null;
    }
  }

  isConnected(): boolean {
    return this.echo !== null;
  }
}

export const websocket = new WebSocketService();
export default websocket;
