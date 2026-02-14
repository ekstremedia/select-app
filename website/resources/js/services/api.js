import axios from 'axios';

const client = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request interceptor: attach auth tokens
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
    auth: {
        guest: (nickname) => client.post('/auth/guest', { nickname }),
        register: (data) => client.post('/auth/register', data),
        login: (data) => client.post('/auth/login', data),
        logout: () => client.post('/auth/logout'),
        me: () => client.get('/auth/me'),
        forgotPassword: (email) => client.post('/auth/forgot-password', { email }),
        resetPassword: (data) => client.post('/auth/reset-password', data),
        twoFactor: {
            enable: () => client.post('/two-factor/enable'),
            confirm: (code) => client.post('/two-factor/confirm', { code }),
            disable: (password) => client.delete('/two-factor/disable', { data: { password } }),
        },
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
        chat: (code, message) => client.post(`/games/${code}/chat`, { message }),
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
        ban: (playerId, reason, banIp) => client.post('/admin/ban', { player_id: playerId, reason, ban_ip: banIp }),
        unban: (playerId) => client.post(`/admin/unban/${playerId}`),
    },
};

export default client;
