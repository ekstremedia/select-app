# Gullkorn Mobile App

React Native (Expo) mobile app for the Gullkorn acronym game.

## Quick Start

```bash
# Install dependencies
yarn install

# Copy and configure environment
cp .env.example .env
# Edit .env with your server's IP address

# Start Expo
yarn start
```

## Configuration

Edit `.env` to point to your backend server:

```env
# For development, use your computer's local IP address
# Find it with: ifconfig (macOS/Linux) or ipconfig (Windows)

EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=192.168.1.100
EXPO_PUBLIC_REVERB_PORT=8080
EXPO_PUBLIC_REVERB_SCHEME=http
EXPO_PUBLIC_REVERB_APP_KEY=gullkorn-key
```

### Special Addresses

| Device              | Use This Address       |
|---------------------|------------------------|
| Android Emulator    | `10.0.2.2`            |
| iOS Simulator       | `localhost`            |
| Physical Device     | Your computer's IP     |

## Running the App

```bash
# Start Expo dev server
yarn start

# Run on specific platform
yarn android
yarn ios
yarn web
```

Scan the QR code with Expo Go app on your phone, or press:
- `a` for Android emulator
- `i` for iOS simulator
- `w` for web browser

## Project Structure

```
├── app/                    # Expo Router screens
│   ├── _layout.tsx        # Root layout with navigation
│   ├── index.tsx          # Home / guest creation
│   ├── create.tsx         # Create game screen
│   ├── join.tsx           # Join game screen
│   ├── profile.tsx        # User profile
│   └── game/
│       └── [code].tsx     # Game screen (all phases)
│
└── src/
    ├── stores/            # Zustand state management
    │   ├── authStore.ts   # Player/user authentication
    │   └── gameStore.ts   # Game state + WebSocket handlers
    ├── services/
    │   ├── api.ts         # REST API client
    │   └── websocket.ts   # Laravel Echo WebSocket
    └── types/
        └── index.ts       # TypeScript definitions
```

## Features

- **Guest Play**: Start playing immediately with just a name
- **Account System**: Optional registration to save stats
- **Real-time**: WebSocket updates for all game events
- **Responsive**: Works on phones and tablets

## Game Flow

1. **Home** - Enter name to play as guest
2. **Create/Join** - Start a new game or join with code
3. **Lobby** - Wait for players, host starts game
4. **Play** - Type sentence matching the acronym
5. **Vote** - Vote for the best answer (not your own)
6. **Results** - See who voted for what
7. **Final** - Winner announcement and leaderboard

## Development

```bash
# TypeScript check
yarn tsc --noEmit

# Format code
yarn prettier --write .

# Build for production
eas build
```

## Troubleshooting

### Can't connect to server

1. Make sure the backend is running (`docker compose up -d` in `website/`)
2. Check your `.env` has the correct IP address
3. Make sure your phone is on the same WiFi network
4. Check firewall isn't blocking ports 8000 and 8080

### WebSocket not connecting

1. Verify Reverb is running: `docker compose logs reverb`
2. Check `REVERB_HOST` matches your `API_URL` host
3. For physical devices, use your actual IP (not localhost)
