import axios from 'axios';
import { getSocketId } from './websocket.js';

const client = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request interceptor: attach auth tokens and socket ID
client.interceptors.request.use((config) => {
    const token = localStorage.getItem('select-auth-token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const guestToken = localStorage.getItem('select-guest-token');
    if (guestToken) {
        config.headers['X-Guest-Token'] = guestToken;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        config.headers['X-CSRF-TOKEN'] = csrfToken;
    }

    // Send socket ID so broadcast()->toOthers() can exclude this client
    const socketId = getSocketId();
    if (socketId) {
        config.headers['X-Socket-ID'] = socketId;
    }

    return config;
});

// Response interceptor: handle common errors
client.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;

        if (status === 401) {
            localStorage.removeItem('select-auth-token');
            localStorage.removeItem('select-guest-token');
            if (window.location.pathname !== '/login' && window.location.pathname !== '/') {
                window.location.href = '/login';
            }
        }

        return Promise.reject(error);
    }
);

export const api = {
    stats: () => client.get('/stats'),
    auth: {
        guest: (nickname) => client.post('/auth/guest', { nickname }),
        register: (data) => client.post('/auth/register', data),
        login: (data) => client.post('/auth/login', data),
        logout: () => client.post('/auth/logout'),
        me: () => client.get('/auth/me'),
        convert: (data) => client.post('/auth/convert', data),
        forgotPassword: (email) => client.post('/auth/forgot-password', { email }),
        resetPassword: (data) => client.post('/auth/reset-password', data),
        twoFactor: {
            enable: () => client.post('/two-factor/enable'),
            confirm: (code) => client.post('/two-factor/confirm', { code }),
            disable: (password) => client.delete('/two-factor/disable', { data: { password } }),
        },
    },
    profile: {
        updateNickname: (nickname) => client.patch('/profile/nickname', { nickname }),
        updatePassword: (data) => client.patch('/profile/password', data),
        deleteAccount: () => client.delete('/profile'),
    },
    games: {
        list: (params) => client.get('/games', { params }),
        create: (data) => client.post('/games', data),
        get: (code) => client.get(`/games/${code}`),
        join: (code, password) => client.post(`/games/${code}/join`, password ? { password } : {}),
        leave: (code) => client.post(`/games/${code}/leave`),
        start: (code) => client.post(`/games/${code}/start`),
        currentRound: (code) => client.get(`/games/${code}/rounds/current`),
        state: (code) => client.get(`/games/${code}/state`),
        chat: (code, message, action = false) => client.post(`/games/${code}/chat`, { message, action }),
        toggleCoHost: (code, playerId) => client.post(`/games/${code}/co-host/${playerId}`),
        updateVisibility: (code, isPublic) => client.patch(`/games/${code}/visibility`, { is_public: isPublic }),
        rematch: (code) => client.post(`/games/${code}/rematch`),
    },
    rounds: {
        submitAnswer: (id, text) => client.post(`/rounds/${id}/answer`, { text }),
        submitVote: (id, answerId) => client.post(`/rounds/${id}/vote`, { answer_id: answerId }),
    },
    players: {
        profile: (nickname) => client.get(`/players/${nickname}`),
        stats: (nickname) => client.get(`/players/${nickname}/stats`),
        sentences: (nickname, params) => client.get(`/players/${nickname}/sentences`, { params }),
        games: (nickname, params) => client.get(`/players/${nickname}/games`, { params }),
    },
    archive: {
        list: (params) => client.get('/archive', { params }),
        get: (code) => client.get(`/archive/${code}`),
        round: (code, roundNumber) => client.get(`/archive/${code}/rounds/${roundNumber}`),
    },
    leaderboard: {
        get: (params) => client.get('/leaderboard', { params }),
    },
    hallOfFame: {
        list: (params) => client.get('/hall-of-fame', { params }),
        random: () => client.get('/hall-of-fame/random'),
    },
    admin: {
        players: (params) => client.get('/admin/players', { params }),
        games: (params) => client.get('/admin/games', { params }),
        stats: () => client.get('/admin/stats'),
        ban: (playerId, reason, banIp) => client.post('/admin/ban', { player_id: playerId, reason, ban_ip: banIp }),
        unban: (playerId) => client.post(`/admin/unban/${playerId}`),
    },
};

export default client;
