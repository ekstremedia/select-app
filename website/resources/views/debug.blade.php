<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select - Debug Console</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a2e;
            color: #eee;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        h1 {
            color: #00d4ff;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
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
        .status.connected {
            background: #00ff88;
            box-shadow: 0 0 10px #00ff88;
        }
        .status.disconnected {
            background: #ff4444;
        }
        .status.connecting {
            background: #ffaa00;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .log {
            background: #0a0a15;
            border-radius: 4px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.6;
        }
        .log-entry {
            margin: 5px 0;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .log-entry.info {
            background: rgba(0, 212, 255, 0.1);
            border-left: 3px solid #00d4ff;
        }
        .log-entry.success {
            background: rgba(0, 255, 136, 0.1);
            border-left: 3px solid #00ff88;
        }
        .log-entry.error {
            background: rgba(255, 68, 68, 0.1);
            border-left: 3px solid #ff4444;
        }
        .log-entry.event {
            background: rgba(233, 69, 96, 0.1);
            border-left: 3px solid #e94560;
        }
        .timestamp {
            color: #666;
            margin-right: 10px;
        }
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
        button:hover {
            background: #ff6b8a;
        }
        button:disabled {
            background: #444;
            cursor: not-allowed;
        }
        button.secondary {
            background: #0f3460;
        }
        button.secondary:hover {
            background: #1a4a7a;
        }
        input, select {
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
        input:focus, select:focus {
            outline: none;
            border-color: #00d4ff;
        }
        .config {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }
        .config label {
            color: #888;
        }
        .config input {
            width: 100%;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .info-box {
            background: #0a0a15;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .info-box strong {
            color: #00d4ff;
        }
    </style>
</head>
<body>
    <h1>Select Debug Console</h1>
    <p class="subtitle">Development tools for testing and debugging</p>

    <div class="grid">
        <!-- WebSocket Panel -->
        <div class="panel">
            <h2>
                <span class="status" id="ws-status"></span>
                WebSocket / Reverb
            </h2>

            <div class="info-box">
                <strong>Reverb Host:</strong> {{ config('reverb.servers.reverb.host') }}<br>
                <strong>Reverb Port:</strong> {{ config('reverb.servers.reverb.port') }}<br>
                <strong>App Key:</strong> {{ config('reverb.apps.0.key') }}
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
                <strong>API URL:</strong> {{ config('app.url') }}/api/v1
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
                Delectus Status
            </h2>

            <div class="info-box">
                <strong>Status:</strong> <span id="delectus-info">Unknown</span>
            </div>

            <button onclick="checkDelectus()">Check Status</button>
            <button class="secondary" onclick="clearLog('delectus-log')">Clear Log</button>

            <div class="log" id="delectus-log"></div>
        </div>
    </div>

    <!-- Pusher JS for Laravel Echo -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        // State
        let pusher = null;
        let currentChannel = null;
        let guestToken = null;
        let gameCode = null;

        // Config from Laravel
        const config = {
            wsHost: '{{ config('reverb.servers.reverb.host') }}',
            wsPort: {{ config('reverb.servers.reverb.port', 8080) }},
            appKey: '{{ config('reverb.apps.0.key') }}',
            apiUrl: '{{ config('app.url') }}/api/v1'
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
            const el = document.getElementById(statusId);
            el.className = 'status ' + state;
        }

        // WebSocket Functions
        function connectWebSocket() {
            const channel = document.getElementById('channel').value;

            log('ws-log', `Connecting to Reverb at ${config.wsHost}:${config.wsPort}...`, 'info');
            setStatus('ws-status', 'connecting');

            try {
                pusher = new Pusher(config.appKey, {
                    wsHost: config.wsHost,
                    wsPort: config.wsPort,
                    forceTLS: false,
                    disableStats: true,
                    enabledTransports: ['ws'],
                    cluster: 'mt1'
                });

                pusher.connection.bind('connected', () => {
                    log('ws-log', 'Connected to Reverb!', 'success');
                    setStatus('ws-status', 'connected');
                    document.getElementById('btn-connect').disabled = true;
                    document.getElementById('btn-disconnect').disabled = false;

                    // Subscribe to channel
                    subscribeToChannel(channel);
                });

                pusher.connection.bind('error', (err) => {
                    log('ws-log', `Connection error: ${JSON.stringify(err)}`, 'error');
                    setStatus('ws-status', 'disconnected');
                });

                pusher.connection.bind('disconnected', () => {
                    log('ws-log', 'Disconnected from Reverb', 'info');
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
            log('ws-log', `Subscribing to channel: ${channelName}`, 'info');

            currentChannel = pusher.subscribe(channelName);

            currentChannel.bind('pusher:subscription_succeeded', () => {
                log('ws-log', `Subscribed to ${channelName}`, 'success');
            });

            currentChannel.bind('pusher:subscription_error', (err) => {
                log('ws-log', `Subscription error: ${JSON.stringify(err)}`, 'error');
            });

            // Bind to all events
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

        // API Functions
        async function apiCall(method, endpoint, body = null) {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            };

            if (guestToken) {
                headers['Authorization'] = `Bearer ${guestToken}`;
            }

            const options = { method, headers };
            if (body) {
                options.body = JSON.stringify(body);
            }

            const response = await fetch(config.apiUrl + endpoint, options);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API Error');
            }

            return data;
        }

        async function testGuestCreate() {
            log('api-log', 'Creating guest player...', 'info');
            setStatus('api-status', 'connecting');

            try {
                const name = 'Debug_' + Math.random().toString(36).substr(2, 5);
                const data = await apiCall('POST', '/auth/guest', { display_name: name });

                guestToken = data.player.guest_token;
                log('api-log', `Guest created: ${data.player.display_name} (ID: ${data.player.id})`, 'success');
                setStatus('api-status', 'connected');
                document.getElementById('btn-create-game').disabled = false;

            } catch (err) {
                log('api-log', `Error: ${err.message}`, 'error');
                setStatus('api-status', 'disconnected');
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

                // Subscribe to game channel
                if (pusher) {
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

        // Delectus Functions
        async function checkDelectus() {
            log('delectus-log', 'Checking Delectus status...', 'info');
            setStatus('delectus-status', 'connecting');

            try {
                // Check for active games
                const response = await fetch(config.apiUrl.replace('/api/v1', '') + '/api/v1/debug/delectus');
                if (response.ok) {
                    const data = await response.json();
                    document.getElementById('delectus-info').textContent =
                        `Active: ${data.active_games}, Waiting: ${data.waiting_games}`;
                    log('delectus-log', `Status: ${JSON.stringify(data)}`, 'success');
                    setStatus('delectus-status', 'connected');
                } else {
                    throw new Error('Delectus endpoint not available');
                }
            } catch (err) {
                log('delectus-log', `Note: Delectus status endpoint not yet implemented`, 'info');
                log('delectus-log', `Check container: docker compose logs delectus`, 'info');
                setStatus('delectus-status', 'disconnected');
            }
        }

        // Init
        log('ws-log', 'Ready. Click Connect to test WebSocket.', 'info');
        log('api-log', 'Ready. Click Create Guest to start.', 'info');
        log('delectus-log', 'Ready. Click Check Status to verify Delectus.', 'info');
    </script>
</body>
</html>
