# Select — Architecture Guide

This document explains how the Select game codebase is organized, how the pieces
fit together, and where to find things. Written for future developers and AI agents.

## Quick Orientation

Select is a real-time multiplayer acronym game. Players get random letters (e.g. "TIHWP"),
write sentences where each word starts with the corresponding letter, then vote on
the best one. It originated as an IRC bot game on #select (EFnet).

**Tech stack:**
- **Backend:** Laravel 12, PostgreSQL, Laravel Reverb (WebSockets)
- **Frontend:** Vue 3, PrimeVue v4 (Aura theme), Tailwind CSS v4, Pinia, Vue Router
- **Mobile:** React Native (Expo) — separate app in `mobileapp/`
- **Infra:** Docker Compose (dev & prod), Apache reverse proxy on production

**Key URLs:**
- Production: https://select.huske.app
- API: `https://select.huske.app/api/v1/`
- WebSocket: `wss://select.huske.app:8082`

---

## Directory Layout

```
website/
├── app/
│   ├── Domain/                    # Pure business logic (no HTTP, no Eloquent)
│   │   ├── Delectus/              # Game orchestrator daemon
│   │   ├── Game/Actions/          # Create, join, leave, start, end game
│   │   ├── Game/Services/         # ScoringService
│   │   ├── Round/Actions/         # Start round, submit answer/vote, complete
│   │   ├── Round/Services/        # AcronymGenerator, AcronymValidator
│   │   └── Player/Actions/        # Guest creation, conversion, ban/unban
│   │
│   ├── Application/               # HTTP layer, jobs, events (depends on Domain)
│   │   ├── Http/Controllers/Api/V1/   # All API controllers
│   │   ├── Http/Middleware/           # ResolvePlayer, RequirePlayer, etc.
│   │   ├── Http/Requests/Api/V1/     # Form Request validation classes
│   │   ├── Broadcasting/Events/       # WebSocket broadcast events
│   │   ├── Jobs/                      # Queue jobs (deadlines, cleanup)
│   │   └── Mail/                      # Email templates
│   │
│   ├── Infrastructure/Models/     # Eloquent models (Game, Round, Player, etc.)
│   ├── Models/User.php            # Laravel User model (Sanctum, auth)
│   └── Console/Commands/          # Artisan commands (Delectus, gullkorn import)
│
├── resources/js/                  # Vue 3 SPA
│   ├── app.js                     # Entry point (Vue + PrimeVue + Pinia + Router)
│   ├── App.vue                    # Root component
│   ├── router.js                  # Vue Router with auth guards
│   ├── pages/                     # 17 page components
│   ├── layouts/                   # AppLayout, GameLayout
│   ├── stores/                    # Pinia stores (auth, game, sound)
│   ├── services/                  # API client (axios), WebSocket (Echo)
│   └── composables/               # useI18n, useDarkMode, useViewport
│
├── routes/
│   ├── api.php                    # All API routes (v1 prefix)
│   ├── web.php                    # SPA catch-all route
│   ├── channels.php               # Presence channel authorization
│   └── console.php                # Scheduled tasks
│
├── database/
│   ├── migrations/                # 15 migrations
│   ├── factories/                 # UserFactory, PlayerFactory
│   └── seeders/                   # UserSeeder, FinishedGameSeeder
│
├── tests/Feature/Api/             # PHPUnit API tests (154 tests)
└── public/sounds/                 # Sound effect MP3 files
```

---

## Architecture: Domain-Driven Design

The backend follows DDD with three layers:

### Domain Layer (`app/Domain/`)

Pure business logic. No HTTP concerns, no Eloquent queries (receives models as params).
Each subdomain has **Actions** (single-responsibility commands) and **Services** (shared logic).

```
Domain/
├── Delectus/           # The game orchestrator (named after the original IRC bot)
│   ├── DelectusService.php    # Finds games needing attention (1-second tick)
│   └── GameProcessor.php      # State machine: processes one game's transitions
│
├── Game/
│   ├── Actions/
│   │   ├── CreateGameAction     # Generates 6-char code, creates game, auto-joins host
│   │   ├── JoinGameAction       # Validates capacity/password, adds player
│   │   ├── LeaveGameAction      # Removes player, transfers host if needed
│   │   ├── StartGameAction      # Validates min players, sets status=playing
│   │   ├── EndGameAction        # Sets finished, saves GameResult, HallOfFame, PlayerStat
│   │   └── GetGameByCodeAction
│   └── Services/
│       └── ScoringService       # Calculates scores from votes, updates stats
│
├── Round/
│   ├── Actions/
│   │   ├── StartRoundAction     # Creates round with generated acronym
│   │   ├── SubmitAnswerAction   # Validates acronym match, stores answer
│   │   ├── StartVotingAction    # Transitions round to voting phase
│   │   ├── SubmitVoteAction     # Records vote, prevents self-voting
│   │   └── CompleteRoundAction  # Calculates round scores, broadcasts results
│   └── Services/
│       ├── AcronymGenerator     # Weighted random letters, ensures vowels
│       └── AcronymValidator     # Checks word count and starting letters
│
└── Player/Actions/
    ├── CreateGuestPlayerAction  # Creates player with UUID + guest_token
    ├── GetPlayerByTokenAction   # Resolves player from guest token
    ├── ConvertGuestToUserAction # Converts guest to registered user
    ├── BanPlayerAction          # Sets ban fields + optional IP ban
    └── UnbanPlayerAction        # Clears ban
```

### Application Layer (`app/Application/`)

HTTP handling, WebSocket events, background jobs. Depends on Domain layer.

**Controllers** receive Form Requests, call Domain Actions, return JSON responses.
They don't contain business logic — that lives in Actions.

**Middleware stack for API requests:**
```
All API requests:
  → ResolvePlayer (global) — resolves player from X-Guest-Token or Sanctum token

Protected routes use aliases:
  → 'player'  = RequirePlayer (401 if no player)
  → 'banned'  = EnsureNotBanned (403 if banned user or IP)
  → 'admin'   = RequireAdmin (403 if not admin role)
```

### Infrastructure Layer (`app/Infrastructure/Models/`)

Eloquent models with relationships, scopes, and casts. The Domain layer receives
these as parameters but doesn't query them directly.

---

## Game State Machine

### Game Lifecycle

```
lobby  ──[host starts]──→  playing  ──[all rounds done]──→  finished
                              │
                        (cycles through rounds)
```

### Round Lifecycle

```
answering  ──[deadline or all submitted]──→  voting  ──[deadline or all voted]──→  completed
```

### Who Drives Transitions?

**Two mechanisms** advance the game state:

1. **Player actions** — Host clicks "Start game", player submits answer, etc.
   Controllers call Domain Actions directly.

2. **Delectus daemon** — Runs in the `select-delectus` container, ticks every second.
   Handles deadline-based transitions (answer time expired, vote time expired).

```
DelectusService.tick()
  → finds games where status = 'playing'
  → for each game: GameProcessor.process(game)
      → checks current round status and deadlines
      → calls StartVotingAction, CompleteRoundAction, StartRoundAction, or EndGameAction
```

**Important:** Both mechanisms can trigger the same actions. The actions are idempotent —
if a player submits the last answer, the controller may transition to voting immediately,
and Delectus will see it's already transitioned and skip it.

---

## Authentication

Two parallel auth systems:

### Guest Auth (X-Guest-Token header)
```
POST /auth/guest { nickname: "Player1" }
  → Creates Player with guest_token (UUID)
  → Client stores token in localStorage
  → All subsequent requests include X-Guest-Token header
```

### Registered Auth (Bearer token via Sanctum)
```
POST /auth/register { email, password, nickname }
  → Creates User + Player
  → Returns Bearer token
  → Client stores in localStorage, sends as Authorization header
```

### Guest Conversion
```
POST /auth/convert { guest_token, email, password }
  → Creates User linked to existing Player
  → Player keeps UUID, stats, game history
  → guest_token cleared, is_guest set to false
```

The `ResolvePlayer` middleware (global on API) resolves the player from whichever
auth method is present and attaches it to the request:
```php
$player = $request->attributes->get('player');
```

---

## WebSocket Events

All game events broadcast on presence channel `game.{code}`:

| Event | When | Payload |
|-------|------|---------|
| `player.joined` | Player joins lobby | player info |
| `player.left` | Player leaves | player id |
| `game.started` | Host starts game | — |
| `round.started` | New round begins | acronym, deadline, round_number |
| `answer.submitted` | Someone submitted | answers_count, total_players |
| `voting.started` | Voting phase begins | anonymized answers, deadline |
| `vote.submitted` | Someone voted | votes_count, total_voters |
| `round.completed` | Round finished | results with scores |
| `game.finished` | Game over | winner, final_scores |
| `chat.message` | Chat message | player, message |

**Channel auth** for guests is handled by `BroadcastAuthController` which accepts
`X-Guest-Token` and generates Pusher auth signatures for presence channels.

---

## Frontend Architecture

### SPA Structure

The app is a single-page application. `routes/web.php` has a catch-all that serves
`welcome.blade.php`, which mounts the Vue app. Vue Router handles all navigation.

### Router Guards

```javascript
// In router.js beforeEach:
requiresAdmin      → redirects non-admins to /
requiresRegistered → redirects guests to /login
requiresPlayer     → redirects unauthenticated to /login
guestOnly          → redirects authenticated users to /
```

### State Management (Pinia)

**authStore** — Player/user session, tokens, auth flows (guest, login, register, convert, logout).
Loads from localStorage on app init.

**gameStore** — Full game state: current game, players, round, answers, votes, phase, chat.
Manages WebSocket subscriptions. Phase state machine: `lobby → answering → voting → results → finished`.

**soundStore** — Sound effects enable/disable and volume. Uses Howler.js. Persists to localStorage.

### Services

**api.js** — Axios client with interceptors for auth tokens and CSRF. Organized by
resource: `api.auth.*`, `api.games.*`, `api.rounds.*`, `api.players.*`, `api.archive.*`,
`api.admin.*`, `api.profile.*`.

**websocket.js** — Laravel Echo client configured for Pusher protocol (connects to Reverb).
Exports `wsJoinGame(code)`, `wsLeaveGame(channel)` helpers.

### Key Pages

| Page | Route | Purpose |
|------|-------|---------|
| `Welcome.vue` | `/` | Landing page with animated acronym demo |
| `Register.vue` | `/register` | Register or convert guest to user |
| `Games.vue` | `/games` | Browse open games, create/join |
| `Game.vue` | `/games/:code` | Full game: lobby, play, vote, results, finish |
| `Archive.vue` | `/archive` | Browse finished games |
| `Leaderboard.vue` | `/leaderboard` | Player rankings |
| `ProfileSettings.vue` | `/profile` | Nickname, password, 2FA, preferences |
| `Admin.vue` | `/admin` | Player/game management, stats |

---

## Database Schema

### Core Tables

```
players
  id (UUID), user_id (FK nullable), guest_token, nickname,
  is_guest, games_played, games_won, total_score, last_active_at

users
  id, name, nickname, email, password, role, is_banned,
  ban_reason, banned_at, two_factor_secret, avatar_url

games
  id, code (6-char unique), host_player_id (FK), status,
  settings (JSON), is_public, password, started_at, finished_at

game_players
  game_id, player_id, score, is_active, joined_at

rounds
  id, game_id, round_number, acronym, status,
  answer_deadline, vote_deadline

answers
  id, round_id, player_id, text, author_nickname,
  votes_count (counter cache)

votes
  id, answer_id, voter_id, voter_nickname
```

### Stats & Archive Tables

```
game_results     — Denormalized finished game data (winner, scores, duration)
hall_of_fame     — All answers with votes (for best-of browsing)
player_stats     — Materialized per-user stats (games, wins, best sentence)
banned_ips       — IP bans for guests
```

### Legacy Tables

```
gullkorn         — Original sentences from IRC #select game
gullkorn_clean   — Curated version of the above
```

Imported via `php artisan gullkorn:import` from MySQL dumps in `website/sql/`.

---

## API Route Groups

```php
// Public (no auth)
GET  /stats
GET  /archive, /archive/{code}, /archive/{code}/rounds/{roundNumber}
GET  /leaderboard, /hall-of-fame, /hall-of-fame/random
GET  /players/{nickname}, /players/{nickname}/stats|sentences|games

// Auth (public)
POST /auth/guest, /auth/register, /auth/login
POST /auth/forgot-password, /auth/reset-password

// Auth (Sanctum protected)
POST   /auth/logout, /auth/convert
PATCH  /profile/password
DELETE /profile

// 2FA (Sanctum protected)
POST   /two-factor/enable, /two-factor/confirm
DELETE /two-factor/disable

// Player + ban checked
GET   /auth/me
PATCH /profile/nickname
POST  /games, /games/{code}/join|leave|start|chat
GET   /games, /games/{code}, /games/{code}/state, /games/{code}/rounds/current
POST  /rounds/{id}/answer|vote|voting|complete

// Admin (Sanctum + admin role)
GET  /admin/players, /admin/games, /admin/stats
POST /admin/ban, /admin/unban/{playerId}
```

---

## Testing

### Backend (PHPUnit)

Tests live in `tests/Feature/Api/`. Run with:
```bash
dc exec select php artisan test --compact                        # All tests
dc exec select php artisan test --compact --filter=ProfileTest   # One file
```

**Test patterns:**
- `RefreshDatabase` trait on all test classes
- `Bus::fake([ProcessAnswerDeadlineJob::class])` in setUp for game tests
- Guest auth via `$this->postJson('/api/v1/auth/guest')` → extract `guest_token`
- Sanctum auth via `User::factory()->create()` → `createToken('api')->plainTextToken`
- Admin auth via `User::factory()->admin()->create()`

### Frontend (Vitest)

Tests live next to source files in `__tests__/` directories. Run with:
```bash
cd website && yarn test        # All tests
yarn test:watch                # Watch mode
```

**Test patterns:**
- `happy-dom` environment
- `createPinia()` / `setActivePinia()` before each store test
- `vi.mock()` for API/WebSocket/sound modules
- `vi.resetModules()` + dynamic import for module-level refs (composables)
- `await nextTick()` for Vue watcher assertions

---

## Docker Services

| Container | Service | Purpose |
|-----------|---------|---------|
| `select` (select-app) | PHP-FPM 8.4 | Application server |
| `select-nginx` | nginx | Web server (port 8000) |
| `select-db` | PostgreSQL 16 | Database (port 5433 external) |
| `select-reverb` | Laravel Reverb | WebSocket server (port 8082) |
| `select-queue` | Queue worker | Background jobs |
| `select-delectus` | Delectus daemon | Game orchestrator (1s tick) |

**Where to run commands:**
```bash
dc exec select php artisan migrate          # Artisan → inside container
dc exec select composer require foo/bar     # Composer → inside container
cd website && yarn build                    # Yarn/Vite → host machine
```

---

## Common Tasks

### Adding a New API Endpoint

1. Create Form Request in `app/Application/Http/Requests/Api/V1/` (check siblings for pattern)
2. Add method to existing controller or create new one
3. Add route to `routes/api.php` in the correct middleware group
4. Add API method to `resources/js/services/api.js`
5. Write test in `tests/Feature/Api/`
6. Run `dc exec select vendor/bin/pint --dirty` for code style

### Adding a New Game Event

1. Create broadcast event in `app/Application/Broadcasting/Events/`
2. Dispatch from the Domain Action that triggers it
3. Handle in `gameStore.js` WebSocket listener
4. Update `Game.vue` (or relevant page) to react to the event

### Adding a New Vue Page

1. Create component in `resources/js/pages/`
2. Add route in `resources/js/router.js` with appropriate guard
3. Add navigation link in `resources/js/layouts/AppLayout.vue`
4. Add translations to `resources/js/composables/useI18n.js` (both NO and EN)
5. Build: `yarn build`

### Adding a New Domain Action

1. Create action class in the appropriate `app/Domain/{subdomain}/Actions/` directory
2. Accept Eloquent models as parameters, return results
3. Call from controller (Application layer)
4. Write test that creates models via factories and calls the API endpoint

---

## Gotchas

- **Host PHP is 8.3, Docker has 8.4** — always run artisan commands in the container
- **`yarn build` runs on host** — built assets go to `public/build/` (gitignored)
- **SQLite in tests** — some PostgreSQL features (ILIKE) don't work; use `like` instead
- **Bus::fake needed in game tests** — `ProcessAnswerDeadlineJob` dispatches synchronously
  in test environment, causing side effects if not faked
- **Guest presence channels** — Custom `BroadcastAuthController` handles guest auth,
  not Laravel's default `/broadcasting/auth`
- **Tailwind v4 dark mode** — Requires `@variant dark (&:where(.dark, .dark *))` override
  in `app.css` for class-based toggling
- **HTTPS behind proxy** — `URL::forceScheme('https')` + `trustProxies(at: '*')` in bootstrap
