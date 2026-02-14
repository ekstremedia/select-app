# SELECT — Implementation Plan

> **What is this document?** A complete, phased implementation plan for "Select" — a real-time multiplayer acronym sentence game. This document is intended for an AI coding assistant (Claude Code) to follow step-by-step. Each phase builds on the previous. Do not skip phases.

> **Repository:** `https://github.com/ekstremedia/select-app`
> **Backend/Website:** `website/` — Laravel 12, Vue 3, PrimeVue v4, Tailwind CSS v4, PostgreSQL, Laravel Reverb
> **Mobile App (later):** `mobileapp/` — React Native (Expo), TypeScript, Zustand

---

## Background & Game Concept

Select is a reimagining of an IRC game from #select on EFnet. The original game worked like this:

1. Players were in the IRC channel `#select`
2. Someone typed `!start`
3. **Delectus** (an eggdrop bot) started the game and displayed an acronym, e.g., `TIHIW`
4. Players privately messaged Delectus with a sentence where each word starts with the corresponding letter: `/msg delectus This Is How It Worked`
5. After 2 minutes, Delectus listed all submitted sentences numbered 1-10+
6. Players voted by messaging Delectus with a number: `/msg delectus 2`
7. After 1 minute, the sentence with the most votes won the round
8. A new acronym appeared shortly after. 8 rounds total.
9. The overall winner was the player with the most round wins / highest score

We are recreating this for the web (and later mobile). The web modernizes it with: multiple simultaneous game lobbies, rich UI, player accounts, public/private games, real-time WebSocket communication, a chat system, stats/leaderboards, and hall of fame.

---

## Architecture Principles

Before coding anything, follow these rules throughout the entire project:

### API-First Design
**Every piece of game logic must be accessible via REST API.** The Vue frontend consumes the API exactly like the mobile app will. No game logic in Blade templates or server-rendered views. The Vue app calls the API, receives JSON, and renders.

### Domain-Driven Design (DDD)
The backend uses DDD. Business logic lives in `app/Domain/`, organized by bounded context (Player, Game, Round, Delectus). Keep Eloquent models in `app/Infrastructure/Models/`. HTTP controllers in `app/Application/Http/`. Events in `app/Application/Broadcasting/Events/`.

### Composition API Only
All Vue components use `<script setup>` with Composition API. No Options API.

### Mobile-First Responsive
Every page and component must work beautifully on phone screens (320px+), tablets, and desktop. Test at 375px width minimum. Pay special attention to keyboard handling during text input.

### Testing
Write tests as you build. PHPUnit Feature tests for every API endpoint. PHPUnit Unit tests for domain services. Vue component tests with Vitest for critical game UI.

### Documentation
Add clear PHPDoc to all PHP classes and methods. Add JSDoc to Vue composables and services. Keep this plan updated as you work.

---

## What Already Exists

The following is already built and working. **Do not rebuild these from scratch** — extend and modify them.

- Docker environment with all containers (app, nginx, db, reverb, queue, delectus)
- Database migrations for: players, games, game_players, rounds, answers, votes
- Domain structure: `app/Domain/Game/`, `app/Domain/Round/`, `app/Domain/Player/`
- Domain actions: CreateGameAction, JoinGameAction, LeaveGameAction, StartGameAction, AcronymGenerator, AcronymValidator, SubmitAnswerAction, SubmitVoteAction, etc.
- API controllers under `app/Application/Http/Controllers/Api/V1/`
- Broadcasting events for game/round lifecycle
- Delectus game orchestrator daemon (`app/Domain/Delectus/`)
- Debug console at `/debug`
- Vue 3 + PrimeVue v4 + Tailwind v4 configured with dark mode and i18n composables
- Welcome page (`resources/js/pages/Welcome.vue`)
- CI/CD for Android builds via GitHub Actions
- Legacy data tables: `gullkorn`, `gullkorn_clean`

**Before starting each phase, read the existing code in the relevant directories to understand current implementations.** Modify and extend rather than rewrite unless the existing code fundamentally doesn't support the requirement.

---

## Phase 1: Authentication & User System

**Goal:** Full auth system supporting both registered users and guest players. Admin capabilities. Transactional emails.

### 1.1 — Database: Users & Auth Tables

Modify/create migrations:

```
users table (modify existing if needed):
  - id: UUID, primary key
  - name: string (full name)
  - email: string, unique
  - nickname: string, unique (3-20 chars, alphanumeric + underscores)
  - password: string (hashed)
  - role: enum('user', 'admin'), default 'user'
  - is_banned: boolean, default false
  - ban_reason: string, nullable
  - banned_at: timestamp, nullable
  - banned_by: UUID, nullable (admin who banned)
  - email_verified_at: timestamp, nullable
  - two_factor_secret: string, nullable (encrypted)
  - two_factor_confirmed_at: timestamp, nullable
  - two_factor_recovery_codes: text, nullable (encrypted)
  - avatar_url: string, nullable
  - remember_token: string, nullable
  - timestamps

players table (modify existing):
  - id: UUID, primary key
  - user_id: UUID, nullable, foreign key → users (null for guests)
  - guest_session_id: string, nullable, unique (for guest players)
  - nickname: string (unique among active guests + all registered users)
  - is_guest: boolean, default true
  - last_active_at: timestamp
  - timestamps

  Unique constraint: nickname must be unique across ALL players (guests + registered).
  Guest records expire after 24 hours of inactivity (cleanup job).
```

### 1.2 — Guest Player Flow

Guest flow (web):
1. User visits the site, not logged in
2. They click "Play as Guest"
3. A modal/page asks for a nickname (validated: unique, 3-20 chars, alphanumeric + underscores)
4. Backend creates a `players` record with `is_guest=true` and generates a `guest_session_id`
5. Session ID stored in an HTTP-only cookie (web) or returned as token (API)
6. Guest can now create/join games
7. Subtle persistent banner: "Playing as guest. Create an account to save your stats."

Guest flow (API — for future mobile app):
1. `POST /api/v1/auth/guest` with `{ nickname }` → returns `{ player, guest_token }`
2. Guest token sent in `X-Guest-Token` header on subsequent requests

Both flows produce the same `player` record. The API works identically regardless of auth method.

### 1.3 — Registration & Login

**Registration:** `POST /api/v1/auth/register`
- Fields: `name`, `email`, `nickname`, `password`, `password_confirmation`
- Validate: email unique, nickname unique (check both users AND active guest players)
- Create user + create linked player record (`is_guest=false`, `user_id` set)
- Send verification email (queued as Laravel job)
- Return auth token (Laravel Sanctum)
- If the user was previously a guest with the same session, migrate their game history to the new account

**Login:** `POST /api/v1/auth/login`
- Fields: `email`, `password`, optional `two_factor_code`
- Check `is_banned` — if true, return 403 with `ban_reason`
- Return Sanctum token
- If 2FA enabled and code not provided, return 422 with `two_factor_required: true`

**Logout:** `POST /api/v1/auth/logout`
- Revoke current token

**Me:** `GET /api/v1/auth/me`
- Returns current player info (works for both guest and registered)

### 1.4 — Password Reset

Standard Laravel password reset flow, but with queued MJML emails:
- `POST /api/v1/auth/forgot-password` → sends reset link email
- `POST /api/v1/auth/reset-password` → resets password with token

### 1.5 — Two-Factor Authentication (2FA)

Use TOTP (Google Authenticator, Authy, etc.):
- `POST /api/v1/auth/two-factor/enable` → returns QR code + secret
- `POST /api/v1/auth/two-factor/confirm` → confirms with code, activates 2FA
- `POST /api/v1/auth/two-factor/disable` → disables 2FA (requires current password)
- Recovery codes generated on enable, viewable once

Consider using `laravel/fortify` or `pragmarx/google2fa-laravel` for the TOTP implementation.

### 1.6 — Admin & Banning

Admin middleware: check `users.role === 'admin'`.

Endpoints:
- `POST /api/v1/admin/ban/{playerId}` — ban player (set `is_banned`, `ban_reason`, `banned_at`, `banned_by`). Immediately disconnect from any active game via WebSocket event. If the player is a guest, also ban by IP (store in a `banned_ips` table).
- `POST /api/v1/admin/unban/{playerId}` — unban player
- `GET /api/v1/admin/players` — list players with search/filter (paginated)
- `GET /api/v1/admin/games` — list all games with status filter

Middleware on all game actions: check if player is banned before allowing any game interaction.

### 1.7 — Transactional Emails with MJML

Install an MJML package (e.g., `spatie/laravel-mjml` or pre-compile MJML → HTML).

Create these email templates (beautiful, branded, responsive):
- **Welcome email** — sent on registration (queued job)
- **Email verification** — sent on registration (queued job)
- **Password reset** — sent on forgot password (queued job)
- **Account banned** — sent when admin bans a registered user (queued job)

All emails dispatched via Laravel Jobs using the `ShouldQueue` interface. Configure queue worker in Docker (`select-queue` container is already set up).

Email design: clean, minimal, game-themed. Include the Select logo/name. Use the game's color scheme.

### 1.8 — Auth Tests

Write Feature tests for:
- Guest creation (valid nickname, duplicate nickname rejection)
- Registration (validation, email sent, player created)
- Login (valid, invalid, banned user, 2FA required)
- Password reset flow
- 2FA enable/confirm/disable
- Admin ban/unban
- Middleware (banned users blocked, admin-only routes)

---

## Phase 2: Vue Frontend Foundation & Routing

**Goal:** Set up Vue Router, layouts, state management, and API/WebSocket services so all subsequent phases can build on top of solid infrastructure.

### 2.1 — Install Dependencies

On the host machine:
```bash
cd website
yarn add vue-router@4 pinia laravel-echo pusher-js howler
yarn add -D vitest @vue/test-utils happy-dom
```

### 2.2 — Vue Router Setup

Configure in `resources/js/app.js` (extend existing setup):

```
Routes:
  /                       → Welcome/landing page (existing, update)
  /login                  → Login page
  /register               → Registration page
  /forgot-password        → Forgot password
  /reset-password/:token  → Reset password
  /profile                → User profile & settings
  /profile/:nickname      → Public player profile (stats)
  /games                  → Game browser (list open games + create/join)
  /games/create           → Create game with settings
  /games/join             → Join game by code
  /games/:code            → Game room (lobby + all gameplay phases)
  /games/:code/spectate   → Spectator view
  /archive                → Game archive (browse all finished games)
  /archive/:code          → Single game archive (full game replay view)
  /leaderboard            → All-time leaderboard
  /hall-of-fame           → Best sentences
  /admin                  → Admin dashboard (admin only)
```

Navigation guards:
- `/admin/*` → require admin role
- `/games/create`, `/games/join` → require auth (guest or registered)
- `/games/:code` → require auth + player is in this game (or redirect to join)
- `/profile` → require registered user

### 2.3 — Layout Components

Create layout components:

**AppLayout.vue** — Main layout wrapper
- Top nav bar: logo, game title, navigation links, user menu
- User menu: nickname display, profile link, dark mode toggle, language toggle (NO/EN), logout
- For guests: "Create Account" button in nav
- Mobile: hamburger menu
- Footer: minimal (links to GitHub, about)
- Slot for page content

**GameLayout.vue** — Game-specific layout (used inside `/games/:code`)
- No full nav bar — minimal header with game code, player count, leave button
- Maximizes game area, especially on mobile
- Handles mobile viewport (see section 2.7)

### 2.4 — Pinia Stores

**authStore.js**
```
State:
  - player: null | Player object
  - user: null | User object (null for guests)
  - isGuest: boolean
  - isAdmin: boolean
  - isAuthenticated: boolean
  - token: string | null

Actions:
  - createGuest(nickname)
  - login(email, password, twoFactorCode?)
  - register(name, email, nickname, password)
  - logout()
  - fetchMe()
  - loadFromStorage() — check cookie/localStorage on app mount

Persist token to localStorage (API) or rely on cookie (web session).
```

**gameStore.js**
```
State:
  - currentGame: null | Game object
  - players: Player[] (reactive list of players in current game)
  - currentRound: null | Round object
  - phase: 'lobby' | 'playing' | 'voting' | 'results' | 'finished'
  - acronym: string
  - answers: Answer[] (visible during voting/results)
  - myAnswer: string | null (what current player submitted)
  - myVote: number | null (answer ID voted for)
  - scores: { playerId: number }
  - deadline: Date | null
  - timeRemaining: number (seconds, counts down)
  - chatMessages: ChatMessage[]
  - isHost: boolean
  - isSpectator: boolean
  - hasSubmittedAnswer: boolean
  - hasVoted: boolean

Actions:
  - fetchGame(code) — GET /api/v1/games/{code}
  - createGame(settings) — POST /api/v1/games
  - joinGame(code, password?) — POST /api/v1/games/{code}/join
  - leaveGame(code) — POST /api/v1/games/{code}/leave
  - startGame(code) — POST /api/v1/games/{code}/start
  - submitAnswer(roundId, text) — POST /api/v1/rounds/{id}/answer
  - submitVote(roundId, answerId) — POST /api/v1/rounds/{id}/vote
  - sendChatMessage(code, message) — POST /api/v1/games/{code}/chat
  - connectWebSocket(code) — join presence channel, bind all events
  - disconnectWebSocket() — leave channel
  - startCountdown() — setInterval decrementing timeRemaining

WebSocket event handlers (update state reactively):
  - onPlayerJoined(data) → add to players[]
  - onPlayerLeft(data) → remove from players[]
  - onGameStarted(data) → phase = 'playing'
  - onRoundStarted(data) → update acronym, deadline, reset round state
  - onAnswerSubmitted(data) → update answer count
  - onVotingStarted(data) → phase = 'voting', populate answers[]
  - onVoteSubmitted(data) → update vote count
  - onRoundCompleted(data) → phase = 'results', populate results + scores
  - onGameFinished(data) → phase = 'finished', final scores
  - onChatMessage(data) → append to chatMessages[]
```

**soundStore.js**
```
State:
  - enabled: boolean (persisted to localStorage)
  - volume: number 0-1 (default 0.3)

Actions:
  - toggle()
  - play(soundName) — only if enabled

Sounds (loaded lazily with Howler.js):
  - 'round-start' — gentle chime when new round begins
  - 'time-warning' — subtle tick when 10 seconds remain
  - 'time-up' — soft gong when deadline passes
  - 'vote-reveal' — whoosh when results appear
  - 'game-win' — victory fanfare (short)
  - 'player-join' — soft pop
  - 'chat-message' — subtle blip
```

### 2.5 — API Service (`resources/js/services/api.js`)

Axios-based service:

```javascript
// Base URL from env or meta tag
// Interceptor: attach Bearer token or guest session cookie
// Interceptor: handle 401 (redirect to login), 403 (banned), 422 (validation errors)
// Interceptor: handle network errors with toast notification

export const api = {
  auth: {
    guest(nickname) {},
    register(data) {},
    login(data) {},
    logout() {},
    me() {},
    forgotPassword(email) {},
    resetPassword(data) {},
    twoFactor: {
      enable() {},
      confirm(code) {},
      disable(password) {},
    },
  },
  games: {
    list(filters?) {},        // GET /api/v1/games
    create(settings) {},      // POST /api/v1/games
    get(code) {},             // GET /api/v1/games/{code}
    join(code, password?) {}, // POST /api/v1/games/{code}/join
    leave(code) {},           // POST /api/v1/games/{code}/leave
    start(code) {},           // POST /api/v1/games/{code}/start
    currentRound(code) {},    // GET /api/v1/games/{code}/rounds/current
    chat(code, message) {},   // POST /api/v1/games/{code}/chat
  },
  rounds: {
    submitAnswer(id, text) {},   // POST /api/v1/rounds/{id}/answer
    submitVote(id, answerId) {}, // POST /api/v1/rounds/{id}/vote
  },
  players: {
    profile(nickname) {},  // GET /api/v1/players/{nickname}
    stats(nickname) {},    // GET /api/v1/players/{nickname}/stats
    games(nickname, page?) {},  // GET /api/v1/players/{nickname}/games
  },
  archive: {
    list(filters?, page?) {},  // GET /api/v1/archive
    get(code) {},              // GET /api/v1/archive/{code}
    round(code, roundNum) {},  // GET /api/v1/archive/{code}/rounds/{roundNumber}
  },
  leaderboard: {
    get(period?) {},       // GET /api/v1/leaderboard
  },
  hallOfFame: {
    list(page?) {},        // GET /api/v1/hall-of-fame
    random() {},           // GET /api/v1/hall-of-fame/random
  },
  admin: {
    players(filters?) {},
    ban(playerId, reason) {},
    unban(playerId) {},
    games(filters?) {},
  },
}
```

### 2.6 — WebSocket Service (`resources/js/services/websocket.js`)

```javascript
// Configure Laravel Echo with Pusher driver pointing to Reverb
// Custom authorizer for presence channels that includes guest session
// Methods:
//   joinGame(code) → subscribe to presence-game.{code}
//   leaveGame(code) → unsubscribe
//   onEvent(eventName, callback) → bind event handlers
//   disconnect() → disconnect Echo

// Auth: custom auth endpoint POST /api/broadcasting/auth
//   - Include CSRF token (web) or Bearer token (API)
//   - Include X-Guest-Token for guest players
//   - The BroadcastAuthController (already exists) handles both auth types
```

### 2.7 — Mobile Viewport Handling

This is critical. Create a composable `useViewport.js`:

```javascript
// Tracks:
//   - viewportHeight (actual visible height, accounting for mobile browser chrome)
//   - keyboardVisible (boolean, detected via visualViewport API or resize events)
//   - keyboardHeight (estimated)
//   - safeAreaBottom

// On mobile, when the keyboard opens:
//   - Game header (acronym, timer) stays fixed at top
//   - Content area shrinks to fit between header and keyboard
//   - Input field stays visible above keyboard
//   - Voting options scroll within the available space

// Use CSS env() for safe areas: env(safe-area-inset-bottom)
// Use 'dvh' units where supported, fallback to JS-calculated height
// Set CSS custom property --vh for use in templates
```

### 2.8 — Phase 2 Tests

- Vitest: test API service methods (mock axios)
- Vitest: test gameStore state transitions
- Vitest: test authStore login/logout/guest flows
- Vitest: test useViewport composable

---

## Phase 3: Game Management (Create, Browse, Join, Lobby)

**Goal:** Players can create games with settings, browse open games, join via code or game list, and wait in a lobby.

### 3.1 — Backend: Game Settings & Discovery

Modify `games` migration to add:
```
is_public: boolean, default true
password: string, nullable (hashed with bcrypt)
```

Modify `settings` JSON column to support all these options:
```json
{
  "rounds": 8,
  "answer_time": 120,
  "vote_time": 60,
  "time_between_rounds": 10,
  "acronym_length_min": 3,
  "acronym_length_max": 6,
  "max_players": 10,
  "excluded_letters": ""
}
```

New/modified API endpoints:

**`GET /api/v1/games`** — List public games in `waiting` status
- Returns: array of games with code, host nickname, player count, max players, settings preview
- Filterable: by player count availability
- Paginated
- Do NOT return password-protected game passwords

**`POST /api/v1/games/{code}/join`** — Modify to accept optional `password` field
- If game has password, validate against hashed password
- If wrong password: 403 "Incorrect password"

Modify `CreateGameAction`:
- Accept full settings object, validate ranges
- Hash password if provided
- Set `is_public`

Modify `AcronymGenerator`:
- Accept `excluded_letters` setting and skip those characters

### 3.2 — Frontend: Game Browser Page (`/games`)

**GamesIndex.vue:**
- Two tabs or sections: "Open Games" / "Create or Join"
- **Open Games list:**
  - Card for each public waiting game
  - Shows: game code, host name, player count / max, round count, answer time
  - "Join" button on each card
  - Password-protected games show lock icon, click prompts for password
  - Auto-refreshes every 10 seconds (or use WebSocket channel for game list updates)
  - Empty state: "No open games. Create one!"
- **Create or Join section:**
  - "Create New Game" button → navigates to `/games/create`
  - "Join by Code" input: 6-character code input → navigates to `/games/:code`

### 3.3 — Frontend: Create Game Page (`/games/create`)

**CreateGame.vue:**
- Form with game settings:
  - Rounds: number input, 1-20, default 8
  - Answer time: slider or select, 30/60/90/120/180/240/300 seconds, default 120
  - Voting time: slider or select, 15/30/45/60/90/120 seconds, default 60
  - Time between rounds: select, 5/10/15/20/30 seconds, default 10
  - Acronym length: range slider (min-max), 3-8, default 3-6
  - Max players: number input, 2-20, default 10
  - Excluded letters: multi-select chip input for letters to exclude (suggest Q, X, Z)
  - Visibility: toggle "Public" / "Private"
  - Password: text input (only shown if Private)
- "Create Game" button
- On success: redirect to `/games/:code` (lobby)
- Use PrimeVue form components: InputNumber, Slider, SelectButton, ToggleButton, InputText, Chips

### 3.4 — Frontend: Join Game Page (`/games/join`)

**JoinGame.vue:**
- Simple page with 6-character code input
- Auto-uppercase, auto-focus, large font
- "Join" button
- Error handling: game not found, game full, wrong password (prompt for password)
- On success: redirect to `/games/:code`

### 3.5 — Frontend: Game Lobby (`/games/:code`, phase=lobby)

The `Game.vue` component handles all phases. When the game status is `waiting`, show the lobby:

**Lobby UI:**
- Game code displayed prominently with copy-to-clipboard button and share link
- QR code for the game URL (nice for in-person play — use a lightweight QR library)
- Player list: avatars/initials, nicknames, "Host" badge on host player
- Real-time updates: players appear/disappear as they join/leave via WebSocket
- Game settings summary (read-only for non-host)
- **Host controls:**
  - "Start Game" button (disabled until min 2 players)
  - Optional: edit settings before starting
- **Non-host view:**
  - "Waiting for host to start the game..."
  - Leave button
- **Chat panel** (see Phase 4.6) — available in lobby too
- Sound: play 'player-join' when someone joins

### 3.6 — Spectator Join

- `GET /api/v1/games/{code}` returns game details even for non-players
- `/games/:code/spectate` connects to WebSocket channel as listener (not presence member)
- Spectators see everything players see but cannot submit answers or vote
- Spectator count shown in lobby and game
- New endpoint: `POST /api/v1/games/{code}/spectate` — registers as spectator (no player record needed, just WebSocket)

### 3.7 — Phase 3 Tests

Backend:
- Test game creation with all settings variations
- Test game listing (only public + waiting games returned)
- Test join with correct/wrong password
- Test join game at capacity → rejected
- Test excluded letters in acronym generation

Frontend:
- Test lobby player list updates on WebSocket events
- Test create game form validation
- Test join game flow

---

## Phase 4: Core Gameplay Loop

**Goal:** The actual game — answering acronyms, voting, results, chat. This is the heart of the app.

### 4.1 — Backend: Review & Modify Round Flow

Review existing domain actions and modify as needed:

**StartRoundAction:**
- Create round with generated acronym (respect `excluded_letters`)
- Set `answer_deadline = now + answer_time` from settings
- Broadcast `round.started` with: `{ round_number, total_rounds, acronym, answer_deadline }`

**SubmitAnswerAction:**
- Validate answer matches acronym (use existing AcronymValidator)
- Validate player hasn't already submitted this round
- Validate deadline hasn't passed
- Store answer (include `author_nickname` denormalized — guests may expire, but we need the name for archive display)
- Broadcast `answer.submitted` with: `{ answers_count, total_players }`
- If all players have submitted → trigger early transition to voting (notify Delectus or directly call StartVotingAction)

**StartVotingAction:**
- Collect all answers for the round
- Shuffle order (so submission order doesn't bias voting)
- Set `vote_deadline = now + vote_time` from settings
- Broadcast `voting.started` with: `{ answers: [{ id, text }], vote_deadline }`
- NOTE: answers are anonymous during voting — do NOT include player info

**SubmitVoteAction:**
- Validate player is in this game
- Validate player hasn't already voted this round
- Validate player is NOT voting for own answer (server-side check, critical!)
- Validate deadline hasn't passed
- Store vote (include `voter_nickname` denormalized — guests may expire, but we need the name for archive display)
- Broadcast `vote.submitted` with: `{ votes_count, total_voters }`
- If all eligible voters have voted → trigger early transition to results

**CompleteRoundAction:**
- Calculate scores from votes
- Broadcast `round.completed` with:
  ```json
  {
    "results": [
      {
        "answer_id": 1,
        "text": "This Is How It Worked",
        "player_nickname": "PlayerOne",
        "votes_count": 3,
        "voters": ["Player2", "Player3", "Player4"],
        "points_earned": 3,
        "is_round_winner": true
      }
    ],
    "scores": [
      { "player_nickname": "PlayerOne", "round_score": 3, "total_score": 7 }
    ],
    "round_number": 3,
    "total_rounds": 8
  }
  ```
- Save to hall_of_fame (see Phase 5)
- After `time_between_rounds` delay: start next round OR finish game

**FinishGameAction:**
- Set `games.finished_at` timestamp and calculate `duration_seconds`
- Calculate final scores
- Determine winner (highest total score; tiebreaker: most round wins)
- Broadcast `game.finished` with: `{ winner, final_scores, game_stats }`
- Save to `game_results` table (denormalized summary for quick access)
- Save round-winning sentences to `hall_of_fame`
- Update `player_stats` for registered players
- **Do NOT delete or clean up `rounds`, `answers`, or `votes` data** — this is the game archive

### 4.2 — Delectus Orchestrator Updates

Modify the existing Delectus daemon to:
- Respect all new settings (time between rounds, etc.)
- Handle early transitions (all players submitted → skip to voting early)
- Post system messages to game chat at key moments:
  - "Round {n} of {total} — Your acronym is: **{ACRONYM}**"
  - "Time's up! Voting begins..."
  - "{nickname} wins the round with {votes} votes!"
  - "Game over! {nickname} wins with {score} points!"
- These messages come through the same `chat.message` WebSocket event with a special `system: true` flag

### 4.3 — Frontend: Playing Phase (Round Active)

When `phase === 'playing'` in gameStore:

**UI Layout (mobile-first):**
```
┌─────────────────────────────┐
│  Round 3/8        ⏱ 1:42   │  ← Fixed header
├─────────────────────────────┤
│                             │
│      T  I  H  I  W         │  ← Acronym, large, spaced out
│                             │
│  ┌─────────────────────┐   │
│  │ This Is How I Win   │   │  ← Input field
│  └─────────────────────┘   │
│                             │
│  Each word preview:         │
│  T: This ✓                  │  ← Real-time validation
│  I: Is ✓                    │
│  H: How ✓                   │
│  I: I ✓                     │
│  W: Win ✓                   │
│                             │
│  [  Submit Answer  ]        │  ← Submit button
│                             │
│  💬 Chat (3)                │  ← Collapsed chat toggle
└─────────────────────────────┘
```

**Answer Input UX — CRITICAL for mobile:**
- Single text input field (NOT one input per letter — that's annoying on mobile)
- As the player types, parse words and show real-time letter matching below the input
- Each word highlighted green ✓ if first letter matches, red ✗ if wrong
- Word count indicator: "3/5 words"
- Submit button disabled until all letters match
- On mobile: when keyboard opens, acronym and timer stay visible at top, input stays above keyboard
- After submitting: input replaced with "Waiting for others... (4/6 submitted)" with a progress indicator
- Prevent double-submit (disable button + check store)

**Timer:**
- Prominent countdown timer in header
- Color changes: green > 30s, yellow 10-30s, red < 10s
- Play 'time-warning' sound at 10 seconds
- Play 'time-up' sound when deadline hits
- Deadline comes from server — calculate time remaining client-side from `answer_deadline` timestamp, NOT from a duration. This ensures all clients are synced.

### 4.4 — Frontend: Voting Phase

When `phase === 'voting'` in gameStore:

**UI Layout:**
```
┌──────────────────────────────┐
│  Voting          ⏱ 0:45     │  ← Fixed header
├──────────────────────────────┤
│  Acronym: T I H I W          │
│                              │
│  ┌──────────────────────┐   │
│  │ 1. This Is How I Win │   │  ← Tappable card
│  └──────────────────────┘   │
│  ┌──────────────────────┐   │
│  │ 2. Turtles In Hats   │   │  ← Selected (highlighted)
│  │    Invade Wyoming     │   │
│  └──────────────────────┘   │
│  ┌──────────────────────┐   │
│  │ 3. Take It Home,     │   │  ← Grayed out (own answer)
│  │    It's Worthless     │   │
│  └──────────────────────┘   │
│                              │
│  [  Confirm Vote  ]         │
│                              │
│  💬 Chat (5)                 │
└──────────────────────────────┘
```

**Voting UX:**
- Answers displayed as cards, numbered
- Player's own answer is visible but grayed out and not selectable (labeled "Your answer")
- Tap a card to select it (highlighted border/background)
- "Confirm Vote" button submits the vote
- After voting: show "Waiting for others... (3/5 voted)"
- Anonymous during voting — no player names shown
- Timer with same color behavior as playing phase

### 4.5 — Frontend: Round Results Phase

When `phase === 'results'` in gameStore:

**UI Layout:**
```
┌──────────────────────────────┐
│  Round 3 Results             │
├──────────────────────────────┤
│  🏆 Round Winner: PlayerOne  │
│                              │
│  "This Is How I Win"         │  ← Winning sentence, large
│  by PlayerOne — 3 votes      │
│                              │
│  ────────────────────────    │
│                              │
│  "Turtles In Hats Invade     │
│   Wyoming"                   │
│  by Player2 — 2 votes        │
│                              │
│  "Take It Home, It's         │
│   Worthless"                 │
│  by Player3 — 0 votes        │
│                              │
│  ── Scoreboard ──            │
│  1. PlayerOne     12 pts     │
│  2. Player2        8 pts     │
│  3. Player3        5 pts     │
│                              │
│  Next round in 8...          │  ← Countdown to next round
│  💬 Chat (7)                 │
└──────────────────────────────┘
```

**Results UX:**
- Results sorted by votes (highest first)
- Winner highlighted with trophy emoji/icon and animation
- Each answer now shows: sentence, author nickname, vote count, who voted for it
- Running scoreboard below results, sorted by total score
- Auto-transition countdown to next round (from `time_between_rounds` setting)
- Play 'vote-reveal' sound when results appear

### 4.6 — Frontend: Game Over Phase

When `phase === 'finished'`:

**UI:**
- Grand winner announcement with celebration animation (confetti? subtle particle effect?)
- Play 'game-win' sound
- Final scoreboard with rankings, total scores, rounds won
- "Best Sentence" highlight — the sentence that got the most votes in any round
- Buttons: "Play Again" (host creates new game, other players auto-invited), "Leave"
- "View in Archive" link → `/archive/:code` (game is now permanently viewable)
- Show hall of fame entry prompt: "This game has been recorded in the Hall of Fame!"

### 4.7 — In-Game Chat

Available during ALL phases (lobby, playing, voting, results, finished):

**Backend:**
- New endpoint: `POST /api/v1/games/{code}/chat` with `{ message }` (max 200 chars)
- Rate limit: 1 message per 2 seconds per player
- Broadcast `chat.message` via WebSocket: `{ nickname, text, timestamp, system: false }`
- System messages from Delectus use `{ nickname: "Delectus", text: "...", timestamp, system: true }`
- Chat messages are NOT persisted to database (in-memory only, lost on game end)

**Frontend:**
- Collapsible chat panel:
  - Desktop: side panel (right side, ~300px wide)
  - Mobile: bottom sheet / drawer, toggle with chat icon + unread count badge
- Chat message list with auto-scroll to bottom
- Input field at bottom of chat panel
- System messages styled differently (italic, different color)
- Player names colored or badged (host gets a crown/star)
- Play 'chat-message' sound on new messages (if chat panel is collapsed)
- Unread message counter on the chat toggle button

### 4.8 — Rejoin Support

If a player refreshes the page or loses connection during a game:

1. On mount of `Game.vue`, call `GET /api/v1/games/{code}` to get full game state
2. Call `GET /api/v1/games/{code}/rounds/current` to get current round state
3. Determine current phase from response and set gameStore accordingly
4. Reconnect to WebSocket presence channel
5. If in playing phase and player hasn't submitted: show input with remaining time
6. If in voting phase and player hasn't voted: show voting UI with remaining time
7. If deadline has passed: show waiting state
8. Show toast: "Reconnected to game"

Backend helper: `GET /api/v1/games/{code}/state` — returns complete game state including:
- Game info, all players, current round, player's submission status, player's vote status, current scores, deadlines

### 4.9 — Anti-Cheat & Validation

Server-side (non-negotiable):
- `AcronymValidator` — verify each word starts with correct letter (already exists, ensure it's solid)
- Prevent duplicate answer submissions (unique constraint: round_id + player_id)
- Prevent duplicate votes (unique constraint: round_id + voter_id)
- Prevent self-voting (compare voter_id with answer's player_id)
- Enforce deadlines server-side (reject submissions after deadline, not just client-side)
- Rate limit answer submissions (1 per round per player)
- Rate limit vote submissions (1 per round per player)
- Sanitize answer text (strip HTML, trim, reasonable max length like 500 chars)

Client-side (UX only, server is source of truth):
- Disable submit after submitting
- Disable vote button for own answer
- Hide submit/vote after deadline

### 4.10 — Phase 4 Tests

Backend:
- Test full game flow: create → join → start → submit answers → vote → results → next round → finish
- Test answer validation (wrong letters, too many words, too few words)
- Test vote validation (self-vote blocked, duplicate vote blocked, after deadline blocked)
- Test scoring calculation
- Test early transition (all players submit early)
- Test Delectus phase transitions
- Test rejoin state endpoint
- Test chat rate limiting

Frontend:
- Test Game.vue phase transitions (mock WebSocket events)
- Test answer input validation (real-time letter matching)
- Test voting UI (own answer disabled)
- Test countdown timer accuracy
- Test chat message rendering

---

## Phase 5: Data Persistence, Stats & Leaderboard

**Goal:** Store historical game data, show player stats, leaderboard, and hall of fame.

### 5.1 — Database: Historical Tables & Data Retention

**IMPORTANT — Data Retention Policy:**
The existing `games`, `game_players`, `rounds`, `answers`, and `votes` tables must be **kept permanently** after games finish. These are the source of truth for the full game archive. Do NOT add any cleanup jobs that delete finished game data. The tables below (`hall_of_fame`, `game_results`, `player_stats`) are **denormalized summaries** for fast display — the original tables remain as the full archive.

Create new migrations:

```
hall_of_fame:
  - id: bigint, auto-increment, primary key
  - game_id: UUID, foreign key → games
  - game_code: string (denormalized for display)
  - round_number: integer
  - acronym: string
  - sentence: string
  - author_nickname: string
  - author_user_id: UUID, nullable, foreign key → users
  - votes_count: integer
  - voter_nicknames: JSON array (e.g., ["Player2", "Player3"])
  - is_round_winner: boolean
  - created_at: timestamp

  Index on: author_user_id, votes_count, created_at

game_results:
  - id: bigint, auto-increment, primary key
  - game_id: UUID, foreign key → games, unique
  - winner_nickname: string
  - winner_user_id: UUID, nullable, foreign key → users
  - final_scores: JSON (array of { nickname, user_id?, score, rounds_won })
  - rounds_played: integer
  - player_count: integer
  - duration_seconds: integer (total game time from start to finish)
  - created_at: timestamp

  Index on: winner_user_id, created_at

Also modify existing `games` table — ensure these columns exist:
  - started_at: timestamp, nullable (set when game starts)
  - finished_at: timestamp, nullable (set when game finishes)
  - status: enum('waiting', 'playing', 'finished', 'cancelled')

Also modify existing `answers` table — ensure these columns exist:
  - author_nickname: string (denormalized — so archive can show author without joins to players who might be guests)
  - created_at: timestamp (when the answer was submitted — needed for archive timeline)

Also modify existing `votes` table — ensure these columns exist:
  - voter_nickname: string (denormalized — so archive can show who voted without joins to players who might be guests)
  - created_at: timestamp

player_stats (materialized / cached stats for performance):
  - id: bigint, auto-increment, primary key
  - user_id: UUID, foreign key → users, unique
  - games_played: integer, default 0
  - games_won: integer, default 0
  - rounds_played: integer, default 0
  - rounds_won: integer, default 0
  - total_votes_received: integer, default 0
  - total_sentences_submitted: integer, default 0
  - best_sentence: string, nullable
  - best_sentence_votes: integer, default 0
  - win_rate: decimal (computed / updated)
  - updated_at: timestamp

  Note: Only tracked for registered users. Guests can see "Create an account to track your stats."
```

### 5.2 — Backend: Record Game Data

**On CompleteRoundAction:**
- Insert all answers for the round into `hall_of_fame`
- Mark the round winner with `is_round_winner = true`
- Update `player_stats` for registered players (increment rounds_played, rounds_won, votes_received, etc.)

**On FinishGameAction:**
- Insert into `game_results`: winner info, all final scores, metadata
- Update `player_stats` for registered players: increment games_played, games_won for winner, update best_sentence if applicable

**Background job:** `UpdatePlayerStatsJob` — dispatched after game finishes, recalculates stats to ensure accuracy. This is a safety net against race conditions.

### 5.3 — API Endpoints for Stats & History

```
GET /api/v1/players/{nickname}
  → Player profile: nickname, avatar, member since, stats summary
  → Works for registered users only (guests have no persistent profile)

GET /api/v1/players/{nickname}/stats
  → Detailed stats: games played/won, rounds played/won, win rate,
    total votes received, best sentences

GET /api/v1/players/{nickname}/sentences
  → Paginated list of their sentences from hall_of_fame (best first)

GET /api/v1/players/{nickname}/games
  → Paginated list of finished games this player participated in
  → Query params: page, per_page, result (won|lost|all)
  → Returns summary per game: code, date, player's rank, score, winner

GET /api/v1/archive
  → Paginated list of all finished games (newest first)
  → Query params: page, per_page, player (nickname filter), period (all|month|week)
  → Returns: game card data (code, date, players, winner, best sentence)

GET /api/v1/archive/{code}
  → Full game detail with every round, every sentence, every vote
  → Cached permanently (finished games never change)
  → See section 5.8 for full response schema

GET /api/v1/archive/{code}/rounds/{roundNumber}
  → Single round detail from a finished game

GET /api/v1/leaderboard
  → Query param: period=all|month|week
  → Top 50 players by games_won (or by total score, configurable)
  → Returns: rank, nickname, games_played, games_won, win_rate

GET /api/v1/hall-of-fame
  → Paginated list of best sentences (ordered by votes_count desc)
  → Query params: period=all|month|week, page, per_page

GET /api/v1/hall-of-fame/random
  → Returns a random hall-of-fame sentence (for welcome page display)
```

### 5.4 — Frontend: Player Profile Page (`/profile/:nickname`)

Public profile page accessible for any registered player:

- Nickname (large), avatar/initial, "Member since" date
- Stats grid: games played, games won, win rate %, rounds won, total votes received
- **Tabs:**
  - **Best Sentences** — top sentences with acronym, sentence text, votes (from hall_of_fame)
  - **Game History** — paginated list of all games played, each linking to `/archive/:code` (see section 5.11)
- If viewing own profile: link to settings/edit profile

### 5.5 — Frontend: My Profile / Settings (`/profile`)

For the logged-in user:

- Edit: display name, nickname (with uniqueness check), avatar
- Change password
- Enable/disable 2FA
- Sound settings (on/off, volume)
- Language preference (NO/EN)
- Dark mode preference
- Account deletion option

### 5.6 — Frontend: Leaderboard Page (`/leaderboard`)

- Period filter tabs: All Time | This Month | This Week
- Table: Rank, Nickname (clickable → profile), Games Played, Games Won, Win Rate, Rounds Won
- Highlight current user's row if logged in
- Top 3 get medal emoji (🥇🥈🥉)
- Use PrimeVue DataTable with sorting

### 5.7 — Frontend: Hall of Fame Page (`/hall-of-fame`)

- Period filter: All Time | This Month | This Week
- Card layout: each sentence as a card showing:
  - Acronym
  - Sentence text (prominent)
  - Author nickname (clickable → profile)
  - Votes count
  - Date
- Paginated (infinite scroll or pagination)
- Random "Classic from IRC" section pulling from `gullkorn_clean` table (link to original game history)

### 5.8 — Backend: Game Archive API

The archive provides a full read-only view of every finished game — every round, every sentence, every vote. The data comes from the existing `games`, `rounds`, `answers`, `votes`, and `game_players` tables (which are kept permanently).

**API Endpoints:**

```
GET /api/v1/archive
  → Paginated list of finished games (newest first)
  → Query params: page, per_page (default 20), player (filter by nickname), period (all|month|week)
  → Returns per game:
    {
      code: "ABC123",
      played_at: "2026-02-14T18:30:00Z",
      duration_seconds: 1240,
      rounds_played: 8,
      player_count: 5,
      players: ["Player1", "Player2", "Player3", "Player4", "Player5"],
      winner: { nickname: "Player1", score: 14 },
      best_sentence: { acronym: "TIHIW", text: "This Is How I Win", votes: 4 }
    }

GET /api/v1/archive/{code}
  → Full game detail: all metadata + summary of every round
  → Returns:
    {
      code: "ABC123",
      status: "finished",
      settings: { rounds: 8, answer_time: 120, ... },
      played_at: "2026-02-14T18:30:00Z",
      finished_at: "2026-02-14T18:50:40Z",
      duration_seconds: 1240,
      players: [
        { nickname: "Player1", score: 14, rounds_won: 3, rank: 1 },
        { nickname: "Player2", score: 10, rounds_won: 2, rank: 2 },
        ...
      ],
      rounds: [
        {
          round_number: 1,
          acronym: "TIHIW",
          answers: [
            {
              text: "This Is How I Win",
              author_nickname: "Player1",
              votes_count: 3,
              voters: ["Player2", "Player3", "Player4"],
              is_round_winner: true
            },
            {
              text: "Turtles In Hats Invade Wyoming",
              author_nickname: "Player2",
              votes_count: 1,
              voters: ["Player5"],
              is_round_winner: false
            },
            ...
          ]
        },
        ...
      ],
      winner: { nickname: "Player1", score: 14 }
    }

GET /api/v1/archive/{code}/rounds/{roundNumber}
  → Single round detail (same data as above but for one round)
  → Useful for deep-linking to a specific round

GET /api/v1/players/{nickname}/games
  → Paginated list of finished games this player participated in
  → Same format as /api/v1/archive but filtered by player
  → Query params: page, per_page, result (won|lost|all)
```

**Backend implementation notes:**
- Use Eloquent eager loading to avoid N+1: `Game::with(['rounds.answers.votes', 'gamePlayers.player'])`
- Cache individual game archive responses (they never change once finished) — use Laravel cache with key `archive:{code}`, no expiry
- For the list endpoint, cache page results for 5 minutes
- Add a `GameArchiveResource` (API Resource) that formats the response consistently
- Add a `RoundArchiveResource` nested within it

### 5.9 — Frontend: Game Archive Browser (`/archive`)

A beautiful, browsable archive of all finished games.

**ArchiveIndex.vue — Game list page:**
```
┌──────────────────────────────────────┐
│  📚 Game Archive                     │
│                                      │
│  Filters: [All Time ▾] [All Players] │
│           [Search by player...]      │
├──────────────────────────────────────┤
│                                      │
│  ┌────────────────────────────────┐  │
│  │ Game #ABC123                   │  │
│  │ Feb 14, 2026 • 8 rounds • 5p  │  │
│  │ 🏆 Player1 (14 pts)           │  │
│  │ ⭐ "This Is How I Win" (4 votes)│ │
│  │                [View Game →]   │  │
│  └────────────────────────────────┘  │
│                                      │
│  ┌────────────────────────────────┐  │
│  │ Game #XYZ789                   │  │
│  │ Feb 13, 2026 • 5 rounds • 3p  │  │
│  │ 🏆 Player2 (9 pts)            │  │
│  │ ⭐ "Cats Always Run Fast" (3v) │  │
│  │                [View Game →]   │  │
│  └────────────────────────────────┘  │
│                                      │
│  [Load more...]                      │
└──────────────────────────────────────┘
```

**UI details:**
- Card-based layout, one card per game
- Each card shows: game code, date/time, round count, player count, winner, best sentence of that game
- Filter by time period: All Time, This Month, This Week
- Search by player nickname (shows only games that player was in)
- Infinite scroll or "Load more" pagination
- Click a card → navigate to `/archive/:code`
- Responsive: single column on mobile, 2 columns on tablet, 3 on desktop

### 5.10 — Frontend: Single Game Archive View (`/archive/:code`)

A detailed, round-by-round view of a completed game. This is the centerpiece of the archive — make it beautiful.

**ArchiveGame.vue:**
```
┌──────────────────────────────────────────┐
│  Game #ABC123                            │
│  February 14, 2026 • 20 min • 8 rounds  │
├──────────────────────────────────────────┤
│                                          │
│  ── Final Standings ──                   │
│  🥇 Player1    14 pts  (3 rounds won)   │
│  🥈 Player2    10 pts  (2 rounds won)   │
│  🥉 Player3     8 pts  (2 rounds won)   │
│     Player4     5 pts  (1 round won)    │
│     Player5     3 pts  (0 rounds won)   │
│                                          │
│  ── Round 1: T I H I W ──               │
│  🏆 "This Is How I Win"                 │
│     by Player1 • 3 votes                │
│     Voted by: Player2, Player3, Player4 │
│                                          │
│     "Turtles In Hats Invade Wyoming"    │
│     by Player2 • 1 vote                 │
│     Voted by: Player5                   │
│                                          │
│     "Today I Had Ice Water"             │
│     by Player3 • 0 votes                │
│                                          │
│  ── Round 2: C A R F ──                 │
│  🏆 "Cats Always Run Fast"             │
│     by Player2 • 4 votes                │
│     Voted by: Player1, Player3, ...     │
│                                          │
│     ... (all answers shown)             │
│                                          │
│  ── Round 3: ... ──                     │
│  ...                                    │
│                                          │
│  [← Back to Archive]  [Share Game 🔗]   │
└──────────────────────────────────────────┘
```

**UI details:**
- Header: game code, date, duration, round count, link to share
- Final standings at the top: all players ranked by score with medal emojis for top 3
- Player nicknames are clickable → link to their profile (if registered)
- Each round displayed as an expandable/collapsible section:
  - Acronym displayed prominently with spaced letters
  - All answers listed, sorted by votes (winner first)
  - Round winner marked with trophy
  - Each answer shows: sentence, author, vote count, who voted for it
  - Answers with 0 votes still shown (every sentence deserves to be seen!)
- On mobile: rounds are accordion-style (tap to expand), only one open at a time to save space
- On desktop: all rounds visible, with smooth scroll
- "Share Game" button → copies URL to clipboard
- Navigation: back to archive list, previous/next game links
- Subtle visual touches: alternating background colors for rounds, vote count visualized as small dots or a mini bar

### 5.11 — Frontend: Player Game History (on Profile Page)

Update the player profile page (`/profile/:nickname`) to include a "Game History" tab/section:

- Shows all games this player participated in (from `/api/v1/players/{nickname}/games`)
- Card for each game: date, result (Won / 2nd / 3rd / etc.), score, rounds won
- Click any game → go to `/archive/:code`
- Filter: All / Wins Only
- Paginated

### 5.12 — Frontend: Welcome Page Update

Update the existing `Welcome.vue`:
- Hero section with game title and description
- "Play Now" CTA button (→ /games)
- Random hall of fame sentence (from API or gullkorn)
- Quick stats: total games played, total sentences written, active players
- How to play section (brief visual explanation)
- "Browse the Archive" link (→ /archive)
- Login / Register links

### 5.13 — Navigation Updates

Add to the main navigation bar (AppLayout.vue):
- **Play** → /games
- **Archive** → /archive
- **Hall of Fame** → /hall-of-fame
- **Leaderboard** → /leaderboard

On mobile hamburger menu: same links, plus Profile and Settings.

### 5.14 — Phase 5 Tests

Backend:
- Test hall_of_fame records created on round complete
- Test game_results records created on game finish
- Test player_stats incremented correctly
- Test leaderboard ordering and period filtering
- Test hall-of-fame pagination
- Test archive list endpoint (pagination, player filter, period filter)
- Test archive game detail endpoint (returns all rounds, answers, votes)
- Test archive round detail endpoint
- Test player game history endpoint
- Test archive caching (same game returns cached response)
- Test archive only returns finished games (not in-progress or waiting)

Frontend:
- Test profile page renders stats correctly
- Test leaderboard sorting
- Test hall-of-fame infinite scroll
- Test archive list rendering and filtering
- Test archive game detail: all rounds render, accordion behavior on mobile
- Test player game history on profile page

---

## Phase 6: Admin Dashboard

**Goal:** Give admins tools to manage the platform.

### 6.1 — Admin Page (`/admin`)

Protected by admin role check. Sections:

**Players:**
- Searchable, paginated player list
- Columns: nickname, email (if registered), type (guest/registered), games played, status (active/banned)
- Actions: view profile, ban (with reason), unban

**Active Games:**
- List of in-progress games
- Columns: code, host, player count, round, status, started at
- Action: force-end game (emergency)

**Stats Overview:**
- Total registered users, active guests, total games played, games today
- Simple charts if time permits (use Chart.js or Recharts equivalent for Vue)

**Reported Content (future):**
- Placeholder for reported sentences/players (implement when needed)

---

## Phase 7: Polish, Performance & Testing

**Goal:** Make everything feel polished, handle edge cases, optimize performance.

### 7.1 — UI Polish

- Consistent use of PrimeVue components throughout (Button, Card, InputText, Dialog, Toast, Badge, ProgressBar, DataTable, Skeleton, Chips, etc.)
- Loading states: skeleton loaders while data loads (use PrimeVue Skeleton)
- Error states: friendly error pages (404, 403, 500)
- Toast notifications (PrimeVue Toast) for: join/leave events, errors, confirmations
- Subtle CSS transitions between game phases (fade, slide)
- Confetti or particle effect on game win (keep it lightweight — a small CSS animation or canvas effect)
- Dark mode: ensure ALL pages look correct in both light and dark
- i18n: translate ALL user-facing strings to both Norwegian (default) and English

### 7.2 — Error Handling & Recovery

- **WebSocket disconnect:**
  - Auto-reconnect with exponential backoff
  - On reconnect: refetch game state from API to sync
  - Show "Reconnecting..." indicator
- **Network errors:**
  - Retry failed API calls (max 3 retries for non-mutating requests)
  - Show toast for failures
  - Queue answer/vote submissions if network is down, retry when back online
- **Edge cases:**
  - Player submits after deadline (server rejects, show "Time's up!" message)
  - Game host disconnects (transfer host to next player — existing logic in LeaveGameAction)
  - All players leave mid-game (Delectus should detect and end the game)
  - Player banned mid-game (force disconnect, notify other players)

### 7.3 — Performance

- Lazy-load route components (Vue Router dynamic imports)
- Sound files: lazy-loaded, small (< 50KB each, MP3/OGG format)
- WebSocket: only subscribe to relevant game channel, unsubscribe on leave
- Debounce chat message sending
- Cleanup intervals/timers on component unmount
- Consider virtualized list for hall-of-fame if it gets very long

### 7.4 — Comprehensive Test Suite

**Backend (PHPUnit):**
- Feature tests for EVERY API endpoint (happy path + error cases)
- Unit tests for domain services (AcronymGenerator, AcronymValidator, ScoringService)
- Unit tests for actions (each action class)
- Integration test: full game lifecycle from create to finish
- Test database seeders for development

**Frontend (Vitest):**
- Unit tests for all Pinia stores (state mutations, actions)
- Unit tests for composables (useViewport, useI18n, useDarkMode)
- Component tests for critical game UI (answer input validation, voting, timer)
- Integration tests for game phase transitions

**E2E (optional, stretch goal):**
- Playwright or Cypress tests for: registration, create game, play full round
- These are nice-to-have, not blocking

### 7.5 — Database Seeders

Create seeders for development:
- `UserSeeder` — admin user + 10 test users
- `GameSeeder` — a few games in various states (waiting, in-progress, finished)
- `FinishedGameSeeder` — 5-10 fully completed games with realistic data: rounds, answers with proper acronym matching, votes, winners. These populate the archive, hall of fame, and leaderboard with meaningful test data.
- `HallOfFameSeeder` — sample historical data (can pull from FinishedGameSeeder output)
- `GullkornSeeder` — ensure gullkorn import works (reference existing import command)

### 7.6 — Documentation

- Update this `SELECT-PLAN.md` with completed status for each phase
- `README.md` in project root: quick start, architecture overview, how to contribute
- `website/README.md`: backend setup, API overview, environment variables
- `mobileapp/README.md`: mobile setup (placeholder, for later)
- API documentation: consider generating OpenAPI/Swagger docs from routes (or maintain a simple markdown API doc)
- Inline code documentation: PHPDoc on all PHP classes, JSDoc on Vue composables

---

## Implementation Order & Priority

Work through phases sequentially. Each phase should be **fully working and tested** before moving to the next.

```
Phase 1: Auth & User System         ← MUST DO FIRST (everything depends on this)
Phase 2: Vue Frontend Foundation     ← Infrastructure for all UI work
Phase 3: Game Management            ← Create, browse, join, lobby
Phase 4: Core Gameplay Loop          ← THE MAIN THING — answering, voting, results, chat
Phase 5: Data & Stats               ← History, profiles, leaderboard, hall of fame
Phase 6: Admin Dashboard            ← Platform management
Phase 7: Polish & Testing           ← Make it production-ready
```

Within each phase, work on backend first (API endpoints + tests), then frontend (Vue pages + component tests). This ensures the API contract is solid before building UI on top of it.

---

## Technical Reminders

- **All game logic goes through the API.** The Vue frontend is a client. No Blade-rendered game logic.
- **Deadlines are timestamps, not durations.** Server sends `deadline: "2024-01-15T12:05:00Z"`, client calculates remaining time. This keeps everyone in sync regardless of network latency.
- **WebSocket events supplement, not replace, API calls.** Events push real-time updates, but the client can always call the API to get full state (important for rejoin).
- **Test on mobile Chrome/Safari during development.** Use browser dev tools responsive mode as minimum, real device testing preferred.
- **Run `yarn build` on host machine, not in Docker.** The Vite build happens outside Docker.
- **Run `php artisan` inside the Docker app container.** Use `docker compose exec app php artisan ...`
- **Existing domain code is DDD-structured.** Follow the pattern: Actions for use cases, Services for reusable logic, Models in Infrastructure.
- **PrimeVue v4 Aura theme** is already configured. Use its component library extensively. Check PrimeVue v4 docs for component APIs.
- **Tailwind v4** is configured with class-based dark mode (`.dark` class on body). PrimeVue dark mode also uses `.dark` selector.
- **i18n:** Use existing `useI18n()` composable. Add all new strings to both NO and EN.

