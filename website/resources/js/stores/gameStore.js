import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '../services/api.js';
import { joinGame as wsJoinGame, leaveGame as wsLeaveGame } from '../services/websocket.js';
import { useSoundStore } from './soundStore.js';

export const useGameStore = defineStore('game', () => {
    const currentGame = ref(null);
    const players = ref([]);
    const currentRound = ref(null);
    const phase = ref(null); // 'lobby' | 'playing' | 'voting' | 'results' | 'finished'
    const acronym = ref('');
    const answers = ref([]);
    const myAnswer = ref(null);
    const myVote = ref(null);
    const scores = ref([]);
    const deadline = ref(null);
    const timeRemaining = ref(0);
    const chatMessages = ref([]);
    const roundResults = ref(null);
    const wsChannel = ref(null);

    let countdownInterval = null;

    const isHost = computed(() => {
        if (!currentGame.value) return false;
        const authStore = _getAuthStore();
        return currentGame.value.host_player_id === authStore?.player?.id;
    });

    const hasSubmittedAnswer = computed(() => myAnswer.value !== null);
    const hasVoted = computed(() => myVote.value !== null);
    const gameCode = computed(() => currentGame.value?.code ?? null);

    let _authStoreRef = null;
    function _getAuthStore() {
        if (!_authStoreRef) {
            try {
                const mod = import.meta.glob('../stores/authStore.js', { eager: true });
                const key = Object.keys(mod)[0];
                _authStoreRef = mod[key].useAuthStore();
            } catch {
                return null;
            }
        }
        return _authStoreRef;
    }

    async function fetchGame(code) {
        const { data } = await api.games.get(code);
        currentGame.value = data.game;
        players.value = data.game.players || [];
        _syncPhase(data.game.status);
        return data;
    }

    async function createGame(settings) {
        const { data } = await api.games.create(settings);
        currentGame.value = data.game;
        players.value = data.game.players || [];
        phase.value = 'lobby';
        return data;
    }

    async function joinGameAction(code, password) {
        const { data } = await api.games.join(code, password);
        currentGame.value = data.game;
        players.value = data.game.players || [];
        _syncPhase(data.game.status);
        return data;
    }

    async function leaveGameAction(code) {
        const { data } = await api.games.leave(code);
        resetState();
        return data;
    }

    async function startGame(code) {
        const { data } = await api.games.start(code);
        currentGame.value = data.game;
        if (data.round) {
            _setRound(data.round);
        }
        phase.value = 'playing';
        return data;
    }

    async function submitAnswer(roundId, text) {
        const { data } = await api.rounds.submitAnswer(roundId, text);
        myAnswer.value = data.answer;
        return data;
    }

    async function submitVote(roundId, answerId) {
        const { data } = await api.rounds.submitVote(roundId, answerId);
        myVote.value = data.vote;
        return data;
    }

    async function sendChatMessage(code, message) {
        const { data } = await api.games.chat(code, message);
        // Add own message locally since toOthers() excludes the sender
        if (data.message) {
            chatMessages.value.push(data.message);
        }
    }

    async function fetchGameState(code) {
        const { data } = await api.games.state(code);
        currentGame.value = data.game;
        players.value = data.game.players || [];
        _syncPhase(data.game.status);
        if (data.round) {
            _setRound(data.round);
        }
        if (data.my_answer) {
            myAnswer.value = data.my_answer;
        }
        if (data.my_vote) {
            myVote.value = data.my_vote;
        }
        if (data.answers) {
            answers.value = data.answers;
        }
        if (data.completed_rounds) {
            // Store completed rounds for display
            currentGame.value.completed_rounds = data.completed_rounds;
        }
        return data;
    }

    async function fetchCurrentRound(code) {
        try {
            const { data } = await api.games.currentRound(code);
            if (data.round) {
                _setRound(data.round);
            }
            return data;
        } catch {
            return null;
        }
    }

    function connectWebSocket(code) {
        if (wsChannel.value) {
            wsLeaveGame(wsChannel.value._code);
        }

        const channel = wsJoinGame(code);
        channel._code = code;
        wsChannel.value = channel;

        channel
            .here((members) => {
                // Presence channel joined
            })
            .joining((member) => {
                useSoundStore().play('player-join');
            })
            .leaving((member) => {
                // Member left
            })
            .listen('.player.joined', (data) => {
                if (data.player && !players.value.find((p) => p.id === data.player.id)) {
                    players.value.push(data.player);
                }
                useSoundStore().play('player-join');
            })
            .listen('.player.left', (data) => {
                players.value = players.value.filter((p) => p.id !== data.player_id);
                if (data.new_host_id && currentGame.value) {
                    currentGame.value.host_player_id = data.new_host_id;
                }
            })
            .listen('.game.started', (data) => {
                phase.value = 'playing';
                if (currentGame.value) {
                    currentGame.value.status = 'playing';
                }
                if (data.round) {
                    _setRound(data.round);
                }
            })
            .listen('.round.started', (data) => {
                phase.value = 'playing';
                myAnswer.value = null;
                myVote.value = null;
                answers.value = [];
                roundResults.value = null;
                _setRound(data);
                useSoundStore().play('round-start');
            })
            .listen('.answer.submitted', (data) => {
                // Update answer count display
                if (currentRound.value) {
                    currentRound.value.answers_count = data.answers_count;
                    currentRound.value.total_players = data.total_players;
                }
            })
            .listen('.voting.started', (data) => {
                phase.value = 'voting';
                answers.value = data.answers || [];
                if (data.vote_deadline) {
                    deadline.value = new Date(data.vote_deadline);
                    _startCountdown();
                }
            })
            .listen('.vote.submitted', (data) => {
                if (currentRound.value) {
                    currentRound.value.votes_count = data.votes_count;
                    currentRound.value.total_voters = data.total_voters;
                }
            })
            .listen('.round.completed', (data) => {
                phase.value = 'results';
                roundResults.value = data.results || [];
                scores.value = data.scores || [];
                _stopCountdown();
                useSoundStore().play('vote-reveal');
            })
            .listen('.game.finished', (data) => {
                phase.value = 'finished';
                scores.value = data.final_scores || [];
                if (currentGame.value) {
                    currentGame.value.status = 'finished';
                    currentGame.value.winner = data.winner;
                }
                _stopCountdown();
                useSoundStore().play('game-win');
            })
            .listen('.chat.message', (data) => {
                chatMessages.value.push(data);
                if (data.system !== true) {
                    useSoundStore().play('chat-message');
                }
            });

        return channel;
    }

    function disconnectWebSocket() {
        if (wsChannel.value) {
            wsLeaveGame(wsChannel.value._code);
            wsChannel.value = null;
        }
    }

    function _setRound(roundData) {
        currentRound.value = roundData;
        acronym.value = roundData.acronym || '';
        if (roundData.answer_deadline) {
            deadline.value = new Date(roundData.answer_deadline);
            _startCountdown();
        }
    }

    function _syncPhase(status) {
        if (status === 'lobby') {
            phase.value = 'lobby';
        } else if (status === 'playing') {
            phase.value = 'playing';
        } else if (status === 'finished') {
            phase.value = 'finished';
        }
    }

    function _startCountdown() {
        _stopCountdown();
        _updateTimeRemaining();
        countdownInterval = setInterval(() => {
            _updateTimeRemaining();
            if (timeRemaining.value <= 10 && timeRemaining.value > 0) {
                useSoundStore().play('time-warning');
            }
            if (timeRemaining.value <= 0) {
                _stopCountdown();
                useSoundStore().play('time-up');
            }
        }, 1000);
    }

    function _stopCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function _updateTimeRemaining() {
        if (!deadline.value) {
            timeRemaining.value = 0;
            return;
        }
        const diff = Math.max(0, Math.floor((deadline.value.getTime() - Date.now()) / 1000));
        timeRemaining.value = diff;
    }

    function resetState() {
        disconnectWebSocket();
        _stopCountdown();
        currentGame.value = null;
        players.value = [];
        currentRound.value = null;
        phase.value = null;
        acronym.value = '';
        answers.value = [];
        myAnswer.value = null;
        myVote.value = null;
        scores.value = [];
        deadline.value = null;
        timeRemaining.value = 0;
        chatMessages.value = [];
        roundResults.value = null;
    }

    return {
        currentGame,
        players,
        currentRound,
        phase,
        acronym,
        answers,
        myAnswer,
        myVote,
        scores,
        deadline,
        timeRemaining,
        chatMessages,
        roundResults,
        isHost,
        hasSubmittedAnswer,
        hasVoted,
        gameCode,
        fetchGame,
        createGame,
        joinGame: joinGameAction,
        leaveGame: leaveGameAction,
        startGame,
        submitAnswer,
        submitVote,
        sendChatMessage,
        fetchGameState,
        fetchCurrentRound,
        connectWebSocket,
        disconnectWebSocket,
        resetState,
    };
});
