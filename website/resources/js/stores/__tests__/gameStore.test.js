import { createPinia, setActivePinia } from 'pinia';
import { useGameStore } from '../gameStore.js';

vi.mock('../../services/api.js', () => ({
    api: {
        auth: {
            guest: vi.fn(),
            login: vi.fn(),
            me: vi.fn(),
        },
        games: {
            get: vi.fn(),
            create: vi.fn(),
            join: vi.fn(),
            leave: vi.fn(),
            start: vi.fn(),
            currentRound: vi.fn(),
            state: vi.fn(),
            chat: vi.fn(),
        },
        rounds: {
            submitAnswer: vi.fn(),
            submitVote: vi.fn(),
        },
    },
}));

vi.mock('../../services/websocket.js', () => ({
    joinGame: vi.fn(() => ({
        _code: null,
        here: vi.fn().mockReturnThis(),
        joining: vi.fn().mockReturnThis(),
        leaving: vi.fn().mockReturnThis(),
        listen: vi.fn().mockReturnThis(),
    })),
    leaveGame: vi.fn(),
}));

vi.mock('../soundStore.js', () => ({
    useSoundStore: vi.fn(() => ({
        play: vi.fn(),
    })),
}));

import { api } from '../../services/api.js';
import { leaveGame as wsLeaveGame } from '../../services/websocket.js';

describe('gameStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('phase is null', () => {
            const store = useGameStore();
            expect(store.phase).toBeNull();
        });

        it('players is empty array', () => {
            const store = useGameStore();
            expect(store.players).toEqual([]);
        });

        it('currentGame is null', () => {
            const store = useGameStore();
            expect(store.currentGame).toBeNull();
        });

        it('currentRound is null', () => {
            const store = useGameStore();
            expect(store.currentRound).toBeNull();
        });

        it('acronym is empty string', () => {
            const store = useGameStore();
            expect(store.acronym).toBe('');
        });

        it('answers is empty array', () => {
            const store = useGameStore();
            expect(store.answers).toEqual([]);
        });

        it('myAnswer is null', () => {
            const store = useGameStore();
            expect(store.myAnswer).toBeNull();
        });

        it('myVote is null', () => {
            const store = useGameStore();
            expect(store.myVote).toBeNull();
        });

        it('scores is empty array', () => {
            const store = useGameStore();
            expect(store.scores).toEqual([]);
        });

        it('deadline is null', () => {
            const store = useGameStore();
            expect(store.deadline).toBeNull();
        });

        it('timeRemaining is 0', () => {
            const store = useGameStore();
            expect(store.timeRemaining).toBe(0);
        });

        it('chatMessages is empty array', () => {
            const store = useGameStore();
            expect(store.chatMessages).toEqual([]);
        });

        it('roundResults is null', () => {
            const store = useGameStore();
            expect(store.roundResults).toBeNull();
        });
    });

    describe('fetchGame', () => {
        it('sets currentGame, players, and phase from response', async () => {
            const mockGame = {
                id: 'game-1',
                code: 'ABCDEF',
                status: 'lobby',
                host_player_id: 'host-uuid',
                players: [
                    { id: 'p1', nickname: 'Player1' },
                    { id: 'p2', nickname: 'Player2' },
                ],
            };
            api.games.get.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            const result = await store.fetchGame('ABCDEF');

            expect(api.games.get).toHaveBeenCalledWith('ABCDEF');
            expect(store.currentGame).toEqual(mockGame);
            expect(store.players).toEqual(mockGame.players);
            expect(store.phase).toBe('lobby');
            expect(result).toEqual({ game: mockGame });
        });

        it('sets phase to playing when status is playing', async () => {
            const mockGame = {
                id: 'game-2',
                code: 'XYZABC',
                status: 'playing',
                players: [],
            };
            api.games.get.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            await store.fetchGame('XYZABC');

            expect(store.phase).toBe('playing');
        });

        it('sets phase to finished when status is finished', async () => {
            const mockGame = {
                id: 'game-3',
                code: 'FINSIH',
                status: 'finished',
                players: [],
            };
            api.games.get.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            await store.fetchGame('FINSIH');

            expect(store.phase).toBe('finished');
        });

        it('defaults players to empty array when game has no players', async () => {
            const mockGame = {
                id: 'game-4',
                code: 'NOPLYR',
                status: 'lobby',
            };
            api.games.get.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            await store.fetchGame('NOPLYR');

            expect(store.players).toEqual([]);
        });
    });

    describe('resetState', () => {
        it('clears everything back to initial state', async () => {
            const mockGame = {
                id: 'game-5',
                code: 'RESETM',
                status: 'playing',
                players: [{ id: 'p1' }],
            };
            api.games.get.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            await store.fetchGame('RESETM');
            store.myAnswer = { id: 'a1', text: 'test' };
            store.myVote = { id: 'v1' };
            store.scores = [{ player_id: 'p1', score: 5 }];
            store.acronym = 'TEST';
            store.answers = [{ id: 'a1' }];
            store.chatMessages = [{ message: 'hello' }];
            store.roundResults = [{ answer_id: 'a1', votes: 3 }];

            store.resetState();

            expect(store.currentGame).toBeNull();
            expect(store.players).toEqual([]);
            expect(store.currentRound).toBeNull();
            expect(store.phase).toBeNull();
            expect(store.acronym).toBe('');
            expect(store.answers).toEqual([]);
            expect(store.myAnswer).toBeNull();
            expect(store.myVote).toBeNull();
            expect(store.scores).toEqual([]);
            expect(store.deadline).toBeNull();
            expect(store.timeRemaining).toBe(0);
            expect(store.chatMessages).toEqual([]);
            expect(store.roundResults).toBeNull();
        });
    });

    describe('_syncPhase (via fetchGame)', () => {
        it('maps lobby status to lobby phase', async () => {
            api.games.get.mockResolvedValue({ data: { game: { status: 'lobby', players: [] } } });
            const store = useGameStore();
            await store.fetchGame('CODE01');
            expect(store.phase).toBe('lobby');
        });

        it('maps playing status to playing phase', async () => {
            api.games.get.mockResolvedValue({ data: { game: { status: 'playing', players: [] } } });
            const store = useGameStore();
            await store.fetchGame('CODE02');
            expect(store.phase).toBe('playing');
        });

        it('maps finished status to finished phase', async () => {
            api.games.get.mockResolvedValue({ data: { game: { status: 'finished', players: [] } } });
            const store = useGameStore();
            await store.fetchGame('CODE03');
            expect(store.phase).toBe('finished');
        });

        it('does not change phase for unknown status', async () => {
            const store = useGameStore();
            store.phase = 'voting';
            api.games.get.mockResolvedValue({ data: { game: { status: 'unknown_status', players: [] } } });
            await store.fetchGame('CODE04');
            // _syncPhase doesn't handle unknown statuses, so phase stays as-is
            expect(store.phase).toBe('voting');
        });
    });

    describe('hasSubmittedAnswer', () => {
        it('returns false when myAnswer is null', () => {
            const store = useGameStore();
            expect(store.hasSubmittedAnswer).toBe(false);
        });

        it('returns true when myAnswer is set', () => {
            const store = useGameStore();
            store.myAnswer = { id: 'a1', text: 'Test answer' };
            expect(store.hasSubmittedAnswer).toBe(true);
        });
    });

    describe('hasVoted', () => {
        it('returns false when myVote is null', () => {
            const store = useGameStore();
            expect(store.hasVoted).toBe(false);
        });

        it('returns true when myVote is set', () => {
            const store = useGameStore();
            store.myVote = { id: 'v1', answer_id: 'a2' };
            expect(store.hasVoted).toBe(true);
        });
    });

    describe('gameCode', () => {
        it('returns null when no current game', () => {
            const store = useGameStore();
            expect(store.gameCode).toBeNull();
        });

        it('returns the game code when currentGame is set', () => {
            const store = useGameStore();
            store.currentGame = { id: 'game-6', code: 'MYCODE' };
            expect(store.gameCode).toBe('MYCODE');
        });
    });

    describe('createGame', () => {
        it('sets currentGame, players, and phase to lobby', async () => {
            const mockGame = {
                id: 'game-7',
                code: 'NEWGAM',
                status: 'lobby',
                players: [{ id: 'host-1', nickname: 'Host' }],
            };
            api.games.create.mockResolvedValue({ data: { game: mockGame } });

            const store = useGameStore();
            const result = await store.createGame({ rounds: 5 });

            expect(api.games.create).toHaveBeenCalledWith({ rounds: 5 });
            expect(store.currentGame).toEqual(mockGame);
            expect(store.players).toEqual(mockGame.players);
            expect(store.phase).toBe('lobby');
            expect(result).toEqual({ game: mockGame });
        });
    });

    describe('submitAnswer', () => {
        it('sets myAnswer from response', async () => {
            const mockAnswer = { id: 'ans-1', text: 'This Is A Test', round_id: 'r1' };
            api.rounds.submitAnswer.mockResolvedValue({ data: { answer: mockAnswer } });

            const store = useGameStore();
            const result = await store.submitAnswer('r1', 'This Is A Test');

            expect(api.rounds.submitAnswer).toHaveBeenCalledWith('r1', 'This Is A Test');
            expect(store.myAnswer).toEqual(mockAnswer);
            expect(result).toEqual({ answer: mockAnswer });
        });
    });

    describe('submitVote', () => {
        it('sets myVote from response', async () => {
            const mockVote = { id: 'vote-1', answer_id: 'ans-2' };
            api.rounds.submitVote.mockResolvedValue({ data: { vote: mockVote } });

            const store = useGameStore();
            const result = await store.submitVote('r1', 'ans-2');

            expect(api.rounds.submitVote).toHaveBeenCalledWith('r1', 'ans-2');
            expect(store.myVote).toEqual(mockVote);
            expect(result).toEqual({ vote: mockVote });
        });
    });

    describe('leaveGame', () => {
        it('calls api and resets state', async () => {
            api.games.leave.mockResolvedValue({ data: { success: true } });
            api.games.get.mockResolvedValue({
                data: { game: { id: 'g1', code: 'LEAVEG', status: 'lobby', players: [{ id: 'p1' }] } },
            });

            const store = useGameStore();
            await store.fetchGame('LEAVEG');
            expect(store.currentGame).not.toBeNull();

            await store.leaveGame('LEAVEG');

            expect(api.games.leave).toHaveBeenCalledWith('LEAVEG');
            expect(store.currentGame).toBeNull();
            expect(store.phase).toBeNull();
            expect(store.players).toEqual([]);
        });
    });

    describe('disconnectWebSocket', () => {
        it('calls wsLeaveGame when channel exists', () => {
            const store = useGameStore();
            store.connectWebSocket('WSCODE');

            store.disconnectWebSocket();

            expect(wsLeaveGame).toHaveBeenCalled();
            // Calling disconnect again should not call wsLeaveGame a second time
            // because the channel was already cleared
            wsLeaveGame.mockClear();
            store.disconnectWebSocket();
            expect(wsLeaveGame).not.toHaveBeenCalled();
        });

        it('does nothing when no channel', () => {
            const store = useGameStore();
            store.disconnectWebSocket();

            expect(wsLeaveGame).not.toHaveBeenCalled();
        });
    });
});
