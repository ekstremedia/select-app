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
│   ├── docker/
│   │   ├── setup.sh          # Auto-runs on first docker compose up
│   │   ├── nginx/            # Nginx configuration
│   │   └── php/              # PHP configuration
│   ├── docker-compose.yml    # Development environment
│   └── Dockerfile
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

Access at `http://select.test:8000/debug` (local/development only).

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

### Prerequisites
Add to `/etc/hosts`:
```
127.0.0.1   select.test
```

### Backend (Docker)
```bash
cd website
docker compose up -d
```

That's it! The first run automatically:
- Creates `.env` from `.env.example`
- Installs Composer dependencies
- Generates application key
- Runs database migrations

**Docker Containers:**
| Container | Purpose | Port |
|-----------|---------|------|
| select-setup | First-time setup (runs once) | - |
| select-app | PHP-FPM application | - |
| select-nginx | Web server | 8000 (configurable) |
| select-db | PostgreSQL database | 5432 (configurable) |
| select-reverb | WebSocket server | 8080 |
| select-queue | Background job worker | - |
| select-delectus | Game orchestrator daemon | - |

**Default URLs:**
- API: http://select.test:8000/api/v1
- WebSocket: ws://select.test:8080
- Debug Console: http://select.test:8000/debug

### Where to Run Commands

| Command | Where | Example |
|---------|-------|---------|
| `composer install` | Inside container | `docker compose exec app composer install` |
| `php artisan ...` | Inside container | `docker compose exec app php artisan migrate` |
| `yarn install` | Host machine | `cd website && yarn install` |
| Edit `.env` | Host machine | Edit `website/.env` directly |

### Useful Docker Commands

```bash
# Start all containers
docker compose up -d

# Stop all containers
docker compose down

# View logs
docker compose logs -f

# View logs for specific service
docker compose logs -f app

# Shell into app container
docker compose exec app bash

# Run artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# Restart a specific service
docker compose restart app

# Reset everything (start fresh)
docker compose down -v --rmi local && rm -rf vendor .env
docker compose up -d
```

### Mobile App
```bash
cd mobileapp
cp .env.example .env
# Edit .env with your server IP
yarn install
yarn start
```

## CI/CD - Android Builds

Automated Android builds using GitHub Actions. Builds run on GitHub's free runners (unlimited for public repos).

**Workflow:** `.github/workflows/eas-build.yml`

### Triggers
- Push to `main` or `testing` branch (only when `mobileapp/` files change)
- Manual trigger via GitHub Actions UI

### What It Does
1. Sets up Node.js, Java 17, Android SDK
2. Runs `expo prebuild` to generate native Android project
3. Signs and builds AAB with Gradle
4. Uploads AAB as downloadable artifact (30-day retention)
5. Optionally submits to Google Play (manual trigger only)

### GitHub Secrets Required

| Secret | Description |
|--------|-------------|
| `ANDROID_KEYSTORE_BASE64` | Release keystore, base64 encoded |
| `ANDROID_KEYSTORE_PASSWORD` | Keystore password |
| `ANDROID_KEY_ALIAS` | Key alias (e.g., `select-key`) |
| `ANDROID_KEY_PASSWORD` | Key password |
| `GOOGLE_SERVICE_ACCOUNT_JSON` | Play Console service account (for auto-upload) |

### Generate New Keystore (if needed)
```bash
docker run --rm -it -v "$(pwd)":/work -w /work eclipse-temurin:17-jdk \
  keytool -genkeypair -v \
  -storetype PKCS12 \
  -keystore select-release.keystore \
  -alias select-key \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000

# Encode for GitHub secret
base64 -i select-release.keystore | pbcopy
```

### Download Build Artifacts
1. Go to https://github.com/ekstremedia/select-app/actions
2. Click on a completed workflow run
3. Download `app-release` artifact (contains AAB file)

### Manual Play Store Upload
1. Download AAB from GitHub Actions artifacts
2. Go to Google Play Console → Release → Testing → Internal testing
3. Create new release and upload AAB

### Automatic Play Store Upload (TODO)
Requires `GOOGLE_SERVICE_ACCOUNT_JSON` secret with Play Console API access.
To enable: trigger workflow manually with "Submit to Google Play" checked.

## Important Files to Know

**CI/CD:**
- `.github/workflows/eas-build.yml` - Android build and release workflow
- `mobileapp/eas.json` - EAS Build configuration (for local builds)

**Docker & Setup:**
- `website/docker-compose.yml` - Container orchestration, all services defined here
- `website/docker/setup.sh` - Auto-setup script (runs on first `docker compose up`)
- `website/.env.example` - Environment template (copied to `.env` automatically)

**Backend:**
- `website/app/Domain/Round/Services/AcronymGenerator.php` - Random acronym generation
- `website/app/Domain/Round/Services/AcronymValidator.php` - Answer validation
- `website/app/Domain/Game/Services/ScoringService.php` - Vote-based scoring
- `website/app/Domain/Delectus/` - Game orchestrator (deadline handling, round transitions)
- `website/app/Application/Broadcasting/Events/` - All WebSocket events
- `website/resources/views/debug.blade.php` - Debug console (WebSocket testing, API testing)

**Mobile App:**
- `mobileapp/src/stores/gameStore.ts` - Central game state with WS handlers
- `mobileapp/app/game/[code].tsx` - All game phases (lobby, play, vote, results)

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

## Broadcasting / Presence Channel Auth

Guest players need special handling for WebSocket presence channel authorization since Laravel's default broadcasting auth assumes authenticated users.

**Custom Auth Endpoint:** `POST /api/broadcasting/auth`
- Located in: `app/Application/Http/Controllers/Api/V1/BroadcastAuthController.php`
- Handles guest tokens via `X-Guest-Token` header
- Generates Pusher auth signatures for presence channels

**Debug Console Auth:**
- Uses custom fetch handler with CSRF token and guest token
- Endpoint: `/api/broadcasting/auth` (not Laravel's default `/broadcasting/auth`)

## Laravel Boost

Laravel Boost v2.0 is installed for AI-assisted development.

**Installation:**
```bash
docker compose exec -it app php artisan boost:install
```

Note: Must run with `-it` flag for interactive prompts, or run from host machine with matching PHP version.

## Future Plans

- Web-based game client (play in browser)
- Sound effects and animations
- Player avatars
- Game history and replays
- Leaderboards
