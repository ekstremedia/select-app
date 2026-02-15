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
        const myId = authStore?.player?.id;
        if (!myId) return false;
        if (currentGame.value.host_player_id === myId) return true;
        // Co-hosts have the same permissions as host
        const me = players.value.find(p => p.id === myId);
        return me?.is_co_host === true;
    });

    const isActualHost = computed(() => {
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
        _syncPhase(data.game.status, data.round?.status);
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
        _syncPhase(data.game.status, data.round?.status);
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

    async function toggleCoHost(code, playerId) {
        const { data } = await api.games.toggleCoHost(code, playerId);
        // Update local player list
        const player = players.value.find(p => p.id === data.player_id);
        if (player) {
            player.is_co_host = data.is_co_host;
        }
        return data;
    }

    async function updateVisibility(code, isPublic) {
        const { data } = await api.games.updateVisibility(code, isPublic);
        if (currentGame.value) {
            currentGame.value.is_public = data.is_public;
        }
        return data;
    }

    async function rematch(code) {
        const { data } = await api.games.rematch(code);
        // Reset and set up the new game
        resetState();
        currentGame.value = data.game;
        players.value = data.game.players || [];
        phase.value = 'lobby';
        return data;
    }

    async function sendChatMessage(code, message, action = false) {
        const { data } = await api.games.chat(code, message, action);
        // Add own message locally since toOthers() excludes the sender
        if (data.message) {
            chatMessages.value.push({ ...data.message, action });
        }
    }

    async function fetchGameState(code) {
        const { data } = await api.games.state(code);
        currentGame.value = data.game;
        players.value = data.game.players || [];

        // Use server-derived phase when available
        if (data.phase) {
            phase.value = data.phase;
        } else {
            _syncPhase(data.game.status, data.round?.status);
        }

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
            // Mark own answers for voting phase recovery
            const myAnswerId = myAnswer.value?.id;
            answers.value = (data.answers || []).map(a => ({
                ...a,
                is_own: a.id === myAnswerId || a.player_id === _getAuthStore()?.player?.id,
            }));
        }
        if (data.completed_rounds) {
            currentGame.value.completed_rounds = data.completed_rounds;
        }

        // Recover round results and scores for results phase
        if (phase.value === 'results' && data.completed_rounds?.length) {
            const lastCompleted = data.completed_rounds[data.completed_rounds.length - 1];
            if (lastCompleted?.answers && !roundResults.value) {
                roundResults.value = lastCompleted.answers.map(a => ({
                    text: a.text,
                    player_nickname: a.player_name,
                    votes_count: a.votes_count,
                }));
            }
            // Rebuild scores from player list
            if (!scores.value.length) {
                scores.value = (data.game.players || [])
                    .map(p => ({ player_id: p.id, nickname: p.nickname, score: p.score ?? 0 }))
                    .sort((a, b) => b.score - a.score);
            }
            // Set a short countdown — Delectus will start the next round soon
            const delay = data.game.settings?.time_between_rounds ?? 10;
            deadline.value = new Date(Date.now() + delay * 1000);
            _startCountdown();
        }

        // Recover finished game state
        if (phase.value === 'finished') {
            if (data.game.winner !== undefined) {
                currentGame.value.winner = data.game.winner;
            }
            // Build scores from players if not already set
            if (!scores.value.length) {
                scores.value = (data.game.players || [])
                    .map(p => ({ player_id: p.id, player_name: p.nickname, score: p.score ?? 0 }))
                    .sort((a, b) => b.score - a.score);
            }
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
                // Mark the player's own answer so they can't vote for it
                const myAnswerId = myAnswer.value?.id;
                answers.value = (data.answers || []).map(a => ({
                    ...a,
                    is_own: a.id === myAnswerId,
                }));
                const voteDeadline = data.vote_deadline || data.deadline;
                if (voteDeadline) {
                    deadline.value = new Date(voteDeadline);
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

                // Start a between-rounds countdown
                const delay = data.time_between_rounds ?? currentGame.value?.settings?.time_between_rounds ?? 10;
                deadline.value = new Date(Date.now() + delay * 1000);
                _startCountdown();
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
            .listen('.game.rematch', (data) => {
                if (data.new_game_code) {
                    // Navigate to the new game — Inertia router is not available here
                    // so use window.location for a full redirect
                    window.location.href = `/games/${data.new_game_code}`;
                }
            })
            .listen('.co_host.changed', (data) => {
                const player = players.value.find(p => p.id === data.player_id);
                if (player) {
                    player.is_co_host = data.is_co_host;
                }
            })
            .listen('.game.settings_changed', (data) => {
                if (currentGame.value && data.is_public !== undefined) {
                    currentGame.value.is_public = data.is_public;
                }
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
        // Normalize field names — HTTP responses use "id"/"answer_deadline",
        // but broadcast events use "round_id"/"deadline"
        currentRound.value = {
            ...roundData,
            id: roundData.id || roundData.round_id,
        };
        acronym.value = roundData.acronym || '';

        // Pick the correct deadline based on round status
        let activeDeadline;
        if (roundData.status === 'voting' && roundData.vote_deadline) {
            activeDeadline = roundData.vote_deadline;
        } else {
            activeDeadline = roundData.answer_deadline || roundData.deadline;
        }

        if (activeDeadline) {
            deadline.value = new Date(activeDeadline);
            _startCountdown();
        }
    }

    function _syncPhase(status, roundStatus = null) {
        if (status === 'lobby') {
            phase.value = 'lobby';
        } else if (status === 'finished') {
            phase.value = 'finished';
        } else if (status === 'playing' || status === 'voting') {
            if (roundStatus === 'voting') {
                phase.value = 'voting';
            } else if (roundStatus === 'completed') {
                phase.value = 'results';
            } else if (roundStatus === 'answering') {
                phase.value = 'playing';
            } else {
                // Fallback based on game status
                phase.value = status === 'voting' ? 'voting' : 'playing';
            }
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
                // Poll game state as fallback in case we missed a WebSocket event
                _pollAfterDeadline();
            }
        }, 1000);
    }

    let _pollTimer = null;
    function _pollAfterDeadline() {
        if (_pollTimer) clearTimeout(_pollTimer);
        const code = currentGame.value?.code;
        if (!code) return;
        // Wait 3 seconds for the WebSocket event, then poll
        _pollTimer = setTimeout(async () => {
            try {
                await fetchGameState(code);
            } catch {
                // ignore
            }
        }, 3000);
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
        if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
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
        isActualHost,
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
        toggleCoHost,
        updateVisibility,
        rematch,
        sendChatMessage,
        fetchGameState,
        fetchCurrentRound,
        connectWebSocket,
        disconnectWebSocket,
        resetState,
    };
});
