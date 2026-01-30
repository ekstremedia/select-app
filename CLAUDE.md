# Select - Acronym Sentence Game

A real-time multiplayer mobile game where players create sentences from random acronyms. Originally an IRC game from #select (EFnet), now reimagined for iOS and Android.

## Project Overview

**Game Flow:**
1. Players join a lobby (2-8 players) via 6-character code
2. Host starts the game
3. Each round: random acronym displayed (e.g., "TIHWP")
4. Players submit sentences where each word starts with corresponding letter ("This Is How We Played")
5. Voting phase: players vote for best answer (can't vote for own)
6. Points awarded based on votes received
7. After all rounds, winner announced

## Project Structure

```
select-app/
├── website/          # Laravel 12 backend (API + WebSockets + future web game)
│   ├── app/
│   │   ├── Domain/           # Pure business logic (DDD)
│   │   │   ├── Game/         # Game, GamePlayer, GameSettings
│   │   │   ├── Round/        # Round, Answer, Vote, Acronym
│   │   │   └── Player/       # Player, GuestToken
│   │   ├── Application/      # HTTP, Broadcasting, Jobs
│   │   │   ├── Http/Controllers/Api/V1/
│   │   │   ├── Broadcasting/Events/
│   │   │   └── Jobs/
│   │   └── Infrastructure/   # Eloquent models
│   │       └── Models/
│   ├── docker-compose.yml    # Development environment
│   ├── Dockerfile
│   └── setup.sh              # One-command setup script
│
└── mobileapp/        # React Native (Expo) mobile app
    ├── app/                  # Expo Router pages
    │   ├── _layout.tsx
    │   ├── index.tsx         # Home/guest creation
    │   ├── create.tsx        # Create game
    │   ├── join.tsx          # Join game
    │   ├── profile.tsx       # User profile
    │   └── game/[code].tsx   # Game screen (all phases)
    └── src/
        ├── stores/           # Zustand state management
        │   ├── authStore.ts
        │   └── gameStore.ts
        ├── services/         # API client, WebSocket
        │   ├── api.ts
        │   └── websocket.ts
        └── types/            # TypeScript definitions
```

## Tech Stack

- **Backend**: Laravel 12, PostgreSQL, Laravel Reverb (WebSockets)
- **Mobile**: React Native (Expo), TypeScript, Zustand
- **Architecture**: DDD (Domain-Driven Design) for backend

## Key Domain Actions

### Player Domain
- `CreateGuestPlayerAction` - Creates guest with unique token
- `GetPlayerByTokenAction` - Auth via guest token
- `ConvertGuestToUserAction` - Convert guest to registered user

### Game Domain
- `CreateGameAction` - Creates game with 6-char code, host joins automatically
- `JoinGameAction` - Join via code, validates capacity
- `LeaveGameAction` - Leave game, transfers host if needed
- `StartGameAction` - Validates min players, starts first round
- `ScoringService` - Calculates scores from votes

### Round Domain
- `AcronymGenerator` - Weighted letter generation, ensures vowels
- `AcronymValidator` - Validates answer matches acronym
- `StartRoundAction` - Creates round with acronym
- `SubmitAnswerAction` - Validates and stores answer
- `StartVotingAction` - Transitions to voting phase
- `SubmitVoteAction` - Records vote, prevents self-voting
- `CompleteRoundAction` - Calculates scores, broadcasts results

### Delectus (Game Orchestrator)
Named after the original IRC bot from #select on EFnet. Runs as a daemon that:
- Monitors all active games every second
- Automatically transitions phases when deadlines pass
- Starts new rounds or ends games
- Located in `app/Domain/Delectus/`
- Runs in `select-delectus` container

## API Endpoints

```
POST   /api/v1/auth/guest          Create guest player
POST   /api/v1/auth/register       Register account
POST   /api/v1/auth/login          Login
POST   /api/v1/auth/convert        Convert guest to user
GET    /api/v1/auth/me             Get current player

POST   /api/v1/games               Create game
GET    /api/v1/games/{code}        Get game details
POST   /api/v1/games/{code}/join   Join game
POST   /api/v1/games/{code}/leave  Leave game
POST   /api/v1/games/{code}/start  Start game (host only)
GET    /api/v1/games/{code}/rounds/current  Get current round

POST   /api/v1/rounds/{id}/answer  Submit answer
POST   /api/v1/rounds/{id}/vote    Submit vote
POST   /api/v1/rounds/{id}/voting  Start voting (host)
POST   /api/v1/rounds/{id}/complete Complete round (host)

GET    /api/v1/debug/delectus      Delectus status (dev only)
```

## Debug Console

Access at `http://select.test:8001/debug` (local/development only).

Features:
- **WebSocket Panel**: Test Reverb connection, subscribe to channels, view events
- **API Panel**: Create guests, games, test endpoints
- **Delectus Panel**: Check game orchestrator status

## WebSocket Events (presence-game.{code})

**Server → Client:**
- `player.joined` / `player.left`
- `game.started`
- `round.started` - { acronym, deadline }
- `answer.submitted` - { answers_count, total_players }
- `voting.started` - { answers[], deadline }
- `vote.submitted` - { votes_count, total_voters }
- `round.completed` - { results[], scores[] }
- `game.finished` - { winner, final_scores }

## Database Schema

- **players** - id (UUID), user_id?, guest_token?, display_name, stats
- **games** - id, code (6-char), host_player_id, status, settings (JSON)
- **game_players** - game_id, player_id, score, is_active
- **rounds** - id, game_id, round_number, acronym, status, deadlines
- **answers** - id, round_id, player_id, text, votes_count
- **votes** - id, answer_id, voter_id

## Development Setup

### Backend (Docker)
```bash
cd website
./setup.sh  # One-command setup
```

**Docker Containers:**
| Container | Purpose | Port |
|-----------|---------|------|
| select-app | PHP-FPM application | - |
| select-nginx | Web server | 8001 (configurable) |
| select-db | PostgreSQL database | 5433 (configurable) |
| select-reverb | WebSocket server | 8080 |
| select-queue | Background job worker | - |
| select-delectus | Game orchestrator daemon | - |

**Default URLs:**
- API: http://select.test:8001/api/v1
- WebSocket: ws://select.test:8080
- Debug Console: http://select.test:8001/debug

### Mobile App
```bash
cd mobileapp
cp .env.example .env
# Edit .env with your server IP
yarn install
yarn start
```

## Important Files to Know

1. `website/app/Domain/Round/Services/AcronymGenerator.php` - Random acronym generation
2. `website/app/Domain/Round/Services/AcronymValidator.php` - Answer validation
3. `website/app/Domain/Game/Services/ScoringService.php` - Vote-based scoring
4. `website/app/Domain/Delectus/` - Game orchestrator (deadline handling, round transitions)
5. `website/app/Application/Broadcasting/Events/` - All WebSocket events
6. `website/resources/views/debug.blade.php` - Debug console (WebSocket testing, API testing)
7. `mobileapp/src/stores/gameStore.ts` - Central game state with WS handlers
8. `mobileapp/app/game/[code].tsx` - All game phases (lobby, play, vote, results)

## Authentication

- **Guest-first**: Players can play immediately with just a display name
- Guest token stored in `X-Guest-Token` header
- Guests can convert to full accounts, preserving stats
- Authenticated users use Bearer tokens via Laravel Sanctum

## Game Settings (Defaults)

```json
{
  "min_players": 2,
  "max_players": 8,
  "rounds": 5,
  "answer_time": 60,
  "vote_time": 30,
  "acronym_length_min": 3,
  "acronym_length_max": 6
}
```

## Future Plans

- Web-based game client (play in browser)
- Sound effects and animations
- Player avatars
- Game history and replays
- Leaderboards
