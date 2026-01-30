# Select Backend

Laravel 12 API backend with real-time WebSocket support for the Select game.

## Quick Start (Docker)

```bash
# Clone and setup
./setup.sh
```

That's it! The script will:
- Create `.env` from `.env.example`
- Build and start Docker containers
- Generate application key
- Run database migrations

## Docker Containers

After setup, you'll have 6 containers running:

| Container | Purpose | Port |
|-----------|---------|------|
| select-app | PHP-FPM application | - |
| select-nginx | Web server (API) | 8001 |
| select-db | PostgreSQL database | 5433 |
| select-reverb | WebSocket server (Laravel Reverb) | 8080 |
| select-queue | Background job worker | - |
| select-delectus | Game orchestrator daemon | - |

**URLs:**
- API: http://localhost:8001/api/v1
- WebSocket: ws://localhost:8080
- Debug Console: http://localhost:8001/debug

## Configuration

### Changing Ports

Edit `.env` before running setup:

```env
APP_PORT=8000      # API port
REVERB_PORT=8080   # WebSocket port
DB_PORT=5432       # Database port
```

### Connecting Mobile App

Find your computer's local IP:
```bash
# macOS/Linux
ifconfig | grep "inet " | grep -v 127.0.0.1

# Windows
ipconfig
```

Update `mobileapp/.env`:
```env
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=192.168.1.100
EXPO_PUBLIC_REVERB_PORT=8080
```

## Docker Commands

```bash
# Start containers
docker compose up -d

# View logs
docker compose logs -f

# View specific service logs
docker compose logs -f app
docker compose logs -f reverb

# Shell into app container
docker compose exec app bash

# Run artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# Stop containers
docker compose down

# Stop and remove volumes (fresh start)
docker compose down -v
```

## Running Tests

```bash
docker compose exec app php artisan test
```

## API Documentation

See the main [CLAUDE.md](../CLAUDE.md) for full API documentation.

### Quick Reference

```bash
# Create guest player
curl -X POST http://localhost:8000/api/v1/auth/guest \
  -H "Content-Type: application/json" \
  -d '{"display_name": "Player1"}'

# Create game (with guest token)
curl -X POST http://localhost:8000/api/v1/games \
  -H "Content-Type: application/json" \
  -H "X-Guest-Token: <token>" \
  -d '{"settings": {"rounds": 5}}'

# Join game
curl -X POST http://localhost:8000/api/v1/games/ABCDEF/join \
  -H "X-Guest-Token: <token>"
```

## Development Without Docker

If you prefer running locally:

```bash
# Requirements: PHP 8.4+, Composer, PostgreSQL

# Install dependencies
composer install

# Setup environment
cp .env.example .env
# Edit .env with your database settings:
# DB_HOST=127.0.0.1
# DB_DATABASE=select
# etc.

# Generate key and migrate
php artisan key:generate
php artisan migrate

# Start servers (in separate terminals)
php artisan serve          # API at :8000
php artisan reverb:start   # WebSocket at :8080
php artisan queue:work     # Queue worker
```

## Project Structure

```
app/
├── Domain/               # Business logic
│   ├── Game/            # Game management
│   ├── Player/          # Authentication
│   ├── Round/           # Gameplay
│   └── Delectus/        # Game orchestrator (auto deadlines, round transitions)
├── Application/         # HTTP layer
│   ├── Broadcasting/    # WebSocket events
│   ├── Http/Controllers/
│   └── Jobs/            # Queue jobs
└── Infrastructure/      # Data layer
    └── Models/          # Eloquent models
```

## Delectus - Game Orchestrator

Delectus is a daemon that automatically manages game flow:
- Monitors all active games every second
- Transitions rounds when deadlines pass (answering → voting → completed)
- Starts new rounds or ends games automatically
- Named after the original IRC bot from #select on EFnet

See `app/Domain/Delectus/README.md` for full documentation.
