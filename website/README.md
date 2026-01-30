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

## Services

After setup, you'll have:

| Service    | URL                      | Description           |
|------------|--------------------------|----------------------|
| API        | http://localhost:8000    | REST API             |
| WebSocket  | ws://localhost:8080      | Laravel Reverb       |
| PostgreSQL | localhost:5432           | Database             |

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
│   └── Round/           # Gameplay
├── Application/         # HTTP layer
│   ├── Broadcasting/    # WebSocket events
│   ├── Http/Controllers/
│   └── Jobs/            # Queue jobs
└── Infrastructure/      # Data layer
    └── Models/          # Eloquent models
```
