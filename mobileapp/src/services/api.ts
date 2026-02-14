import {
  AuthResponse,
  GameResponse,
  RoundResponse,
  Answer,
  Vote,
  Game,
  Round
} from '../types';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

class ApiClient {
  private guestToken: string | null = null;
  private authToken: string | null = null;

  setGuestToken(token: string | null) {
    this.guestToken = token;
  }

  setAuthToken(token: string | null) {
    this.authToken = token;
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    if (this.authToken) {
      (headers as Record<string, string>)['Authorization'] = `Bearer ${this.authToken}`;
    }

    if (this.guestToken) {
      (headers as Record<string, string>)['X-Guest-Token'] = this.guestToken;
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Unknown error' }));
      throw new Error(error.error || error.message || 'Request failed');
    }

    return response.json();
  }

  // Auth endpoints
  async createGuest(nickname: string): Promise<AuthResponse> {
    return this.request('/auth/guest', {
      method: 'POST',
      body: JSON.stringify({ nickname }),
    });
  }

  async register(email: string, password: string, name?: string, guestToken?: string): Promise<AuthResponse> {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        password_confirmation: password,
        name,
        guest_token: guestToken,
      }),
    });
  }

  async login(email: string, password: string): Promise<AuthResponse> {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
  }

  async convertGuest(guestToken: string, email: string, password: string, name?: string): Promise<AuthResponse> {
    return this.request('/auth/convert', {
      method: 'POST',
      body: JSON.stringify({
        guest_token: guestToken,
        email,
        password,
        password_confirmation: password,
        name,
      }),
    });
  }

  async getMe(): Promise<AuthResponse> {
    return this.request('/auth/me');
  }

  // Game endpoints
  async createGame(settings?: Partial<Game['settings']>): Promise<GameResponse> {
    return this.request('/games', {
      method: 'POST',
      body: JSON.stringify({ settings }),
    });
  }

  async getGame(code: string): Promise<GameResponse> {
    return this.request(`/games/${code}`);
  }

  async joinGame(code: string): Promise<GameResponse> {
    return this.request(`/games/${code}/join`, {
      method: 'POST',
    });
  }

  async leaveGame(code: string): Promise<{ success: boolean }> {
    return this.request(`/games/${code}/leave`, {
      method: 'POST',
    });
  }

  async startGame(code: string): Promise<GameResponse & { round: Round }> {
    return this.request(`/games/${code}/start`, {
      method: 'POST',
    });
  }

  // Round endpoints
  async getCurrentRound(code: string): Promise<RoundResponse> {
    return this.request(`/games/${code}/rounds/current`);
  }

  async submitAnswer(roundId: string, text: string): Promise<{ answer: Answer }> {
    return this.request(`/rounds/${roundId}/answer`, {
      method: 'POST',
      body: JSON.stringify({ text }),
    });
  }

  async submitVote(roundId: string, answerId: string): Promise<{ vote: Vote }> {
    return this.request(`/rounds/${roundId}/vote`, {
      method: 'POST',
      body: JSON.stringify({ answer_id: answerId }),
    });
  }

  async startVoting(roundId: string): Promise<RoundResponse> {
    return this.request(`/rounds/${roundId}/voting`, {
      method: 'POST',
    });
  }

  async completeRound(roundId: string): Promise<any> {
    return this.request(`/rounds/${roundId}/complete`, {
      method: 'POST',
    });
  }
}

export const api = new ApiClient();
export default api;
