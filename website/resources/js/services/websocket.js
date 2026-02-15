import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

function getReverbConfig() {
    return {
        key: document.querySelector('meta[name="reverb-key"]')?.content || '',
        port: parseInt(document.querySelector('meta[name="reverb-port"]')?.content || '8080', 10),
    };
}

function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    const wsHost = window.location.hostname;
    const reverbConfig = getReverbConfig();
    const wsPort = reverbConfig.port;
    const appKey = reverbConfig.key;
    const useTLS = window.location.protocol === 'https:';

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: wsHost,
        wsPort: useTLS ? 443 : wsPort,
        wssPort: useTLS ? wsPort : 443,
        forceTLS: useTLS,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                const token = localStorage.getItem('select-auth-token');
                if (token) {
                    headers['Authorization'] = `Bearer ${token}`;
                }

                const guestToken = localStorage.getItem('select-guest-token');
                if (guestToken) {
                    headers['X-Guest-Token'] = guestToken;
                }

                fetch('/api/broadcasting/auth', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(`Auth failed: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then((data) => callback(null, data))
                    .catch((error) => callback(error, null));
            },
        }),
    });

    return echoInstance;
}

export function joinGame(code) {
    return getEcho().join(`game.${code}`);
}

export function leaveGame(code) {
    getEcho().leave(`game.${code}`);
}

export function listenToGame(code) {
    return getEcho().channel(`game.${code}`);
}

export function disconnect() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

export function getConnectionState() {
    if (!echoInstance) {
        return 'disconnected';
    }
    return echoInstance.connector.pusher.connection.state;
}

export function getSocketId() {
    if (!echoInstance) {
        return null;
    }
    return echoInstance.socketId();
}

export default { joinGame, leaveGame, listenToGame, disconnect, getConnectionState, getSocketId };
