# Select Backend

Laravel 12 API backend with real-time WebSocket support and Vue 3 web frontend for the Select acronym game.

## Prerequisites

- Docker Engine with Compose plugin
- Node.js + Yarn (for frontend builds on host)
- Add to `/etc/hosts`: `127.0.0.1 select.test`

## Quick Start

```bash
cd website

# 1. Start Docker (first run auto-installs everything)
docker compose up -d

# 2. Build frontend assets
yarn install
yarn build
```

That's it! The first `docker compose up` automatically:
- Creates `.env` from `.env.example`
- Installs Composer dependencies
- Generates application key
- Runs database migrations
- Sets storage permissions

Visit: http://select.test:8000

## Docker Containers

| Container | Service | Purpose | Port |
|-----------|---------|---------|------|
| select-setup | setup | First-time setup (runs once, then exits) | - |
| select-app | select | PHP-FPM application | - |
| select-nginx | nginx | Web server | 8000 |
| select-db | db | PostgreSQL 16 database | 5432 |
| select-reverb | reverb | WebSocket server (Laravel Reverb) | 8080 |
| select-queue | queue | Background job worker | - |
| select-delectus | delectus | Game orchestrator daemon | - |

**Default URLs:**
- Web/API: http://select.test:8000
- API base: http://select.test:8000/api/v1
- WebSocket: ws://select.test:8080
- Debug Console: http://select.test:8000/debug

## Common Commands

The Docker service name is `select` (not `app`). On the production server, `dc` is aliased to `docker compose`.

### Docker

```bash
# Start all containers
docker compose up -d

# Stop all containers
docker compose down

# View all logs
docker compose logs -f

# View logs for specific service
docker compose logs -f select
docker compose logs -f reverb
docker compose logs -f delectus

# Shell into app container
docker compose exec select bash

# Restart a specific service
docker compose restart select

# Reset everything (fresh start — destroys database!)
docker compose down -v --rmi local && rm -rf vendor .env
docker compose up -d
```

### Artisan Commands

All artisan commands must run **inside the container** (host has PHP 8.3, project requires 8.4+):

```bash
# Run migrations
docker compose exec select php artisan migrate

# Open tinker REPL
docker compose exec -it select php artisan tinker

# Clear all caches
docker compose exec select php artisan optimize:clear

# Run Delectus game orchestrator (normally runs in its own container)
docker compose exec select php artisan delectus:run

# Import legacy gullkorn data
docker compose exec select php artisan gullkorn:import
docker compose exec select php artisan gullkorn:import --only=gullkorn_clean

# List all available commands
docker compose exec select php artisan list
```

### Frontend Build

Frontend assets (Vue 3 + PrimeVue + Tailwind) are built on the **host machine**:

```bash
cd website

# Install dependencies
yarn install

# Production build (outputs to public/build/)
yarn build

# Development server with HMR
yarn dev
```

## Running Tests

```bash
# Run all tests
docker compose exec select php artisan test --compact

# Run a specific test file
docker compose exec select php artisan test --compact tests/Feature/WelcomePageTest.php

# Run tests matching a name
docker compose exec select php artisan test --compact --filter=test_welcome_page

# Run only unit tests
docker compose exec select php artisan test --compact tests/Unit/

# Run only feature tests
docker compose exec select php artisan test --compact tests/Feature/

# Run with verbose output
docker compose exec select php artisan test tests/Feature/WelcomePageTest.php
```

### Test Structure

```
tests/
├── Feature/
│   ├── ExampleTest.php           # Basic smoke test
│   ├── WelcomePageTest.php       # Welcome page route tests
│   └── Api/
│       ├── AuthTest.php          # Guest creation, registration, login
│       ├── GameTest.php          # Game CRUD, join/leave
│       └── RoundTest.php         # Answers, voting, round flow
└── Unit/
    ├── ExampleTest.php
    └── Domain/
        ├── AcronymGeneratorTest.php
        └── AcronymValidatorTest.php
```

### Code Style

```bash
# Fix code style (run inside container)
docker compose exec select vendor/bin/pint --dirty
```

## Configuration

### Changing Ports

Edit `.env` before starting:

```env
APP_PORT=8000           # Web server port
REVERB_PORT=8080        # WebSocket port
DB_EXTERNAL_PORT=5432   # PostgreSQL external port
```

### Production vs Development

| Setting | Development | Production |
|---------|-------------|------------|
| APP_ENV | local | production |
| APP_DEBUG | true | false |
| APP_URL | http://select.test:8000 | https://select.huske.app |
| DB_EXTERNAL_PORT | 5432 | 5433 |
| REVERB_PORT | 8080 | 8082 |
| LOG_LEVEL | debug | warning |

### Connecting Mobile App

Find your local IP and update `mobileapp/.env`:

```env
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=192.168.1.100
EXPO_PUBLIC_REVERB_PORT=8080
```

## Frontend Stack

- **Vue 3** — SFC components in `resources/js/pages/`
- **PrimeVue v4** — UI components with Aura theme
- **Tailwind CSS v4** — Utility-first styling
- **Vite** — Build tool with Vue + Tailwind plugins

Key files:
- `vite.config.js` — Vite configuration
- `resources/js/app.js` — Vue app entry point, PrimeVue setup
- `resources/css/app.css` — Tailwind + PrimeVue imports
- `resources/js/pages/Welcome.vue` — Welcome page
- `resources/js/composables/` — Reusable Vue composables (i18n, dark mode)

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands (Delectus, Gullkorn import)
├── Domain/               # Business logic (DDD)
│   ├── Game/             # Game management
│   ├── Player/           # Authentication
│   ├── Round/            # Gameplay (acronyms, answers, votes)
│   └── Delectus/         # Game orchestrator daemon
├── Application/          # HTTP layer
│   ├── Broadcasting/     # WebSocket events
│   ├── Http/Controllers/
│   └── Jobs/             # Queue jobs
└── Infrastructure/       # Data layer
    └── Models/           # Eloquent models

resources/
├── js/
│   ├── app.js            # Vue 3 + PrimeVue entry
│   ├── composables/      # useI18n, useDarkMode
│   └── pages/            # Vue page components
├── css/app.css           # Tailwind + PrimeVue
└── views/                # Blade templates

sql/                      # Legacy MySQL dumps for import
```

## API Quick Reference

```bash
# Create guest player
curl -X POST http://select.test:8000/api/v1/auth/guest \
  -H "Content-Type: application/json" \
  -d '{"display_name": "Player1"}'

# Create game (with guest token)
curl -X POST http://select.test:8000/api/v1/games \
  -H "Content-Type: application/json" \
  -H "X-Guest-Token: <token>" \
  -d '{"settings": {"rounds": 5}}'

# Join game
curl -X POST http://select.test:8000/api/v1/games/ABCDEF/join \
  -H "X-Guest-Token: <token>"
```

See [CLAUDE.md](../CLAUDE.md) for full API documentation, WebSocket events, and game flow details.
