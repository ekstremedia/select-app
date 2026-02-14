<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Select - Debug Console</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a2e;
            color: #eee;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        h1 { color: #00d4ff; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .panel {
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #0f3460;
        }
        .panel h2 {
            margin-top: 0;
            color: #e94560;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #666;
        }
        .status.connected { background: #00ff88; box-shadow: 0 0 10px #00ff88; }
        .status.disconnected { background: #ff4444; }
        .status.connecting { background: #ffaa00; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .log {
            background: #0a0a15;
            border-radius: 4px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.6;
        }
        .log-entry { margin: 5px 0; padding: 5px 10px; border-radius: 3px; }
        .log-entry.info { background: rgba(0, 212, 255, 0.1); border-left: 3px solid #00d4ff; }
        .log-entry.success { background: rgba(0, 255, 136, 0.1); border-left: 3px solid #00ff88; }
        .log-entry.error { background: rgba(255, 68, 68, 0.1); border-left: 3px solid #ff4444; }
        .log-entry.event { background: rgba(233, 69, 96, 0.1); border-left: 3px solid #e94560; }
        .timestamp { color: #666; margin-right: 10px; }
        button {
            background: #e94560;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        button:hover { background: #ff6b8a; }
        button:disabled { background: #444; cursor: not-allowed; }
        button.secondary { background: #0f3460; }
        button.secondary:hover { background: #1a4a7a; }
        input {
            background: #0a0a15;
            border: 1px solid #0f3460;
            color: #eee;
            padding: 10px;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        input:focus { outline: none; border-color: #00d4ff; }
        .config { display: grid; grid-template-columns: 120px 1fr; gap: 10px; align-items: center; margin-bottom: 15px; }
        .config label { color: #888; }
        .config input { width: 100%; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        .info-box { background: #0a0a15; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .info-box strong { color: #00d4ff; }
    </style>
</head>
<body>
    <h1>Select Debug Console</h1>
    <p class="subtitle">Development tools for testing and debugging - Auto-checking services...</p>

    <div class="grid">
        <!-- WebSocket Panel -->
        <div class="panel">
            <h2>
                <span class="status" id="ws-status"></span>
                WebSocket / Reverb
            </h2>
            <div class="info-box">
                <strong>Host:</strong> <span id="ws-host-display">-</span><br>
                <strong>Port:</strong> <span id="ws-port-display">-</span><br>
                <strong>App Key:</strong> {{ config('reverb.apps.apps.0.key') }}
            </div>
            <div class="config">
                <label>Channel:</label>
                <input type="text" id="channel" value="test-channel" placeholder="Channel name">
            </div>
            <button id="btn-connect" onclick="connectWebSocket()">Connect</button>
            <button id="btn-disconnect" onclick="disconnectWebSocket()" disabled>Disconnect</button>
            <button class="secondary" onclick="clearLog('ws-log')">Clear Log</button>
            <div class="log" id="ws-log"></div>
        </div>

        <!-- API Test Panel -->
        <div class="panel">
            <h2>
                <span class="status" id="api-status"></span>
                API Test
            </h2>
            <div class="info-box">
                <strong>API URL:</strong> <span id="api-url-display">-</span>
            </div>
            <button onclick="testGuestCreate()">Create Guest</button>
            <button onclick="testGameCreate()" id="btn-create-game" disabled>Create Game</button>
            <button onclick="testJoinGame()" id="btn-join-game" disabled>Join Game</button>
            <button class="secondary" onclick="clearLog('api-log')">Clear Log</button>
            <div class="log" id="api-log"></div>
        </div>

        <!-- Delectus Panel -->
        <div class="panel">
            <h2>
                <span class="status" id="delectus-status"></span>
                Delectus (Game Orchestrator)
            </h2>
            <div class="info-box">
                <strong>Active Games:</strong> <span id="delectus-active">-</span><br>
                <strong>Waiting Games:</strong> <span id="delectus-waiting">-</span>
            </div>
            <button onclick="checkDelectus()">Refresh Status</button>
            <button class="secondary" onclick="clearLog('delectus-log')">Clear Log</button>
            <div class="log" id="delectus-log"></div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        // State
        let pusher = null;
        let currentChannel = null;
        let guestToken = null;
        let gameCode = null;

        // Config - auto-detect from current page
        const config = {
            wsHost: window.location.hostname,
            wsPort: {{ env('REVERB_PORT', 8080) }},
            appKey: '{{ config('reverb.apps.apps.0.key') }}',
            apiUrl: window.location.origin + '/api/v1'
        };

        // Logging
        function log(logId, message, type = 'info') {
            const logEl = document.getElementById(logId);
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `<span class="timestamp">[${time}]</span> ${message}`;
            logEl.appendChild(entry);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function clearLog(logId) {
            document.getElementById(logId).innerHTML = '';
        }

        function setStatus(statusId, state) {
            document.getElementById(statusId).className = 'status ' + state;
        }

        // ============ WebSocket Functions ============
        function connectWebSocket(autoTest = false) {
            const channel = document.getElementById('channel').value;

            log('ws-log', `Connecting to ws://${config.wsHost}:${config.wsPort}...`, 'info');
            setStatus('ws-status', 'connecting');

            try {
                // Enable Pusher logging for debugging
                if (!autoTest) {
                    Pusher.logToConsole = true;
                }

                pusher = new Pusher(config.appKey, {
                    wsHost: config.wsHost,
                    wsPort: config.wsPort,
                    wssPort: config.wsPort,
                    forceTLS: false,
                    disableStats: true,
                    enabledTransports: ['ws', 'wss'],
                    cluster: 'mt1',
                    channelAuthorization: {
                        transport: 'ajax',
                        endpoint: '/api/broadcasting/auth',
                        customHandler: (params, callback) => {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            const headers = {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            };
                            if (guestToken) {
                                headers['X-Guest-Token'] = guestToken;
                            }
                            fetch('/api/broadcasting/auth', {
                                method: 'POST',
                                headers: headers,
                                body: new URLSearchParams({
                                    socket_id: params.socketId,
                                    channel_name: params.channelName
                                })
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`Auth failed: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => callback(null, data))
                            .catch(error => callback(error, null));
                        }
                    }
                });

                pusher.connection.bind('connected', () => {
                    log('ws-log', 'Connected to Reverb!', 'success');
                    setStatus('ws-status', 'connected');
                    document.getElementById('btn-connect').disabled = true;
                    document.getElementById('btn-disconnect').disabled = false;
                    if (!autoTest) {
                        subscribeToChannel(channel);
                    }
                });

                pusher.connection.bind('error', (err) => {
                    const errMsg = err?.error?.data?.message || JSON.stringify(err);
                    log('ws-log', `Connection error: ${errMsg}`, 'error');
                    setStatus('ws-status', 'disconnected');
                    document.getElementById('btn-connect').disabled = false;
                    document.getElementById('btn-disconnect').disabled = true;
                });

                pusher.connection.bind('disconnected', () => {
                    log('ws-log', 'Disconnected', 'info');
                    setStatus('ws-status', 'disconnected');
                    document.getElementById('btn-connect').disabled = false;
                    document.getElementById('btn-disconnect').disabled = true;
                });

                pusher.connection.bind('failed', () => {
                    log('ws-log', 'Connection failed - is Reverb running?', 'error');
                    setStatus('ws-status', 'disconnected');
                    document.getElementById('btn-connect').disabled = false;
                    document.getElementById('btn-disconnect').disabled = true;
                });

            } catch (err) {
                log('ws-log', `Error: ${err.message}`, 'error');
                setStatus('ws-status', 'disconnected');
            }
        }

        function subscribeToChannel(channelName) {
            log('ws-log', `Subscribing to: ${channelName}`, 'info');
            currentChannel = pusher.subscribe(channelName);

            currentChannel.bind('pusher:subscription_succeeded', () => {
                log('ws-log', `Subscribed to ${channelName}`, 'success');
            });

            currentChannel.bind('pusher:subscription_error', (err) => {
                log('ws-log', `Subscription error: ${JSON.stringify(err)}`, 'error');
            });

            currentChannel.bind_global((event, data) => {
                if (!event.startsWith('pusher:')) {
                    log('ws-log', `Event: ${event} - ${JSON.stringify(data)}`, 'event');
                }
            });
        }

        function disconnectWebSocket() {
            if (pusher) {
                pusher.disconnect();
                pusher = null;
                currentChannel = null;
            }
        }

        // ============ API Functions ============
        async function apiCall(method, endpoint, body = null) {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            };
            if (guestToken) {
                headers['X-Guest-Token'] = guestToken;
            }
            const options = { method, headers };
            if (body) {
                options.body = JSON.stringify(body);
            }
            const response = await fetch(config.apiUrl + endpoint, options);
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || data.error || 'API Error');
            }
            return data;
        }

        async function checkApiHealth() {
            log('api-log', 'Checking API...', 'info');
            setStatus('api-status', 'connecting');
            try {
                // Try to hit the guest endpoint validation (will fail but proves API is up)
                const response = await fetch(config.apiUrl + '/auth/guest', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({})
                });
                // Any response means the API is running
                log('api-log', 'API is responding', 'success');
                setStatus('api-status', 'connected');
            } catch (err) {
                log('api-log', `API not reachable: ${err.message}`, 'error');
                setStatus('api-status', 'disconnected');
            }
        }

        async function testGuestCreate() {
            log('api-log', 'Creating guest player...', 'info');
            try {
                const name = 'Debug_' + Math.random().toString(36).substr(2, 5);
                const data = await apiCall('POST', '/auth/guest', { nickname: name });
                guestToken = data.player.guest_token;
                log('api-log', `Guest created: ${data.player.nickname}`, 'success');
                setStatus('api-status', 'connected');
                document.getElementById('btn-create-game').disabled = false;
            } catch (err) {
                log('api-log', `Error: ${err.message}`, 'error');
            }
        }

        async function testGameCreate() {
            log('api-log', 'Creating game...', 'info');
            try {
                const data = await apiCall('POST', '/games', {
                    settings: { rounds: 3, answer_time: 60, vote_time: 30 }
                });
                gameCode = data.game.code;
                log('api-log', `Game created: ${gameCode}`, 'success');
                document.getElementById('btn-join-game').disabled = false;
                if (pusher && pusher.connection.state === 'connected') {
                    subscribeToChannel('presence-game.' + gameCode);
                }
            } catch (err) {
                log('api-log', `Error: ${err.message}`, 'error');
            }
        }

        async function testJoinGame() {
            if (!gameCode) {
                log('api-log', 'No game code available', 'error');
                return;
            }
            log('api-log', `Joining game ${gameCode}...`, 'info');
            try {
                const data = await apiCall('POST', `/games/${gameCode}/join`);
                log('api-log', `Joined game! Players: ${data.game.players?.length || 0}`, 'success');
            } catch (err) {
                log('api-log', `Error: ${err.message}`, 'error');
            }
        }

        // ============ Delectus Functions ============
        async function checkDelectus() {
            log('delectus-log', 'Checking Delectus...', 'info');
            setStatus('delectus-status', 'connecting');
            try {
                const response = await fetch(config.apiUrl + '/debug/delectus');
                if (response.ok) {
                    const data = await response.json();
                    document.getElementById('delectus-active').textContent = data.active_games;
                    document.getElementById('delectus-waiting').textContent = data.waiting_games;
                    log('delectus-log', `Delectus is running - ${data.active_games} active, ${data.waiting_games} waiting`, 'success');
                    setStatus('delectus-status', 'connected');
                } else {
                    throw new Error('Endpoint returned ' + response.status);
                }
            } catch (err) {
                log('delectus-log', `Delectus check failed: ${err.message}`, 'error');
                setStatus('delectus-status', 'disconnected');
            }
        }

        // ============ Auto-check on page load ============
        async function initChecks() {
            // Display config
            document.getElementById('ws-host-display').textContent = config.wsHost;
            document.getElementById('ws-port-display').textContent = config.wsPort;
            document.getElementById('api-url-display').textContent = config.apiUrl;

            // Auto-check all services
            log('ws-log', 'Auto-checking WebSocket...', 'info');
            log('api-log', 'Auto-checking API...', 'info');
            log('delectus-log', 'Auto-checking Delectus...', 'info');

            // Check API
            await checkApiHealth();

            // Check Delectus
            await checkDelectus();

            // Check WebSocket
            connectWebSocket(true);
        }

        // Run on page load
        initChecks();
    </script>
</body>
</html>
