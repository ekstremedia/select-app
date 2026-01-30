# Select

A real-time multiplayer game where players create sentences from random acronyms.

Originally an IRC game from #select (EFnet), now reimagined for mobile.

## How to Play

1. Players join a lobby via 6-character code
2. Host starts the game
3. Each round shows a random acronym (e.g., "TIHWP")
4. Everyone writes a sentence where each word starts with the corresponding letter
   - "TIHWP" → "This Is How We Play"
5. Vote for the best answer (can't vote for your own)
6. Most votes wins the round
7. After all rounds, the player with most points wins!

## Quick Start

### 1. Start the Backend

```bash
cd website
./setup.sh
```

This starts:
- API server at http://localhost:8000
- WebSocket server at ws://localhost:8080
- PostgreSQL database

### 2. Run the Mobile App

```bash
cd mobileapp
yarn install
cp .env.example .env

# Edit .env with your computer's IP address
# e.g., EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1

yarn start
```

Scan QR code with Expo Go on your phone.

## Project Structure

```
select-app/
├── website/        # Laravel 12 backend
│   ├── app/
│   │   ├── Domain/        # Business logic (DDD)
│   │   ├── Application/   # HTTP, WebSocket events
│   │   └── Infrastructure/# Database models
│   ├── docker-compose.yml
│   └── setup.sh           # One-command setup
│
├── mobileapp/      # React Native (Expo) app
│   ├── app/               # Screens (Expo Router)
│   └── src/               # Stores, services, types
│
└── CLAUDE.md       # Detailed technical docs
```

## Tech Stack

- **Backend**: Laravel 12, PostgreSQL, Laravel Reverb (WebSockets)
- **Mobile**: React Native (Expo), TypeScript, Zustand
- **DevOps**: Docker Compose for easy setup

## Documentation

- [Backend README](website/README.md) - API setup, Docker commands
- [Mobile README](mobileapp/README.md) - App setup, configuration
- [CLAUDE.md](CLAUDE.md) - Full technical documentation

## License

MIT
# select-app
