# Changelog

All notable changes to the Select backend will be documented in this file.

## [Unreleased]

### Added
- Docker Compose development environment
- PostgreSQL database configuration
- One-command setup script (setup.sh)
- Nginx reverse proxy configuration
- Laravel Reverb WebSocket configuration
- Initial Laravel 12 project setup with DDD architecture
- Domain layer with Game, Round, and Player domains
- Guest authentication system with token-based auth
- User registration and login with Laravel Sanctum
- Guest-to-user account conversion
- Game creation with customizable settings
- Game lobby with join/leave functionality
- Real-time player presence via WebSockets
- Round system with random acronym generation
- Answer submission with live validation
- Voting phase with self-vote prevention
- Scoring system based on votes received
- WebSocket broadcast events for all game states
- Database migrations for all entities
- Unit tests for AcronymGenerator and AcronymValidator
- Feature tests for Auth, Game, and Round APIs
- **Delectus** - Game orchestrator daemon (automatic deadline handling, round transitions)
- Debug console at /debug (WebSocket testing, API testing, Delectus status)
- GameTestSeeder for development testing

### API Endpoints
- POST /api/v1/auth/guest - Create guest player
- POST /api/v1/auth/register - Register account
- POST /api/v1/auth/login - Login
- POST /api/v1/auth/convert - Convert guest to user
- GET /api/v1/auth/me - Get current player
- POST /api/v1/games - Create game
- GET /api/v1/games/{code} - Get game details
- POST /api/v1/games/{code}/join - Join game
- POST /api/v1/games/{code}/leave - Leave game
- POST /api/v1/games/{code}/start - Start game
- GET /api/v1/games/{code}/rounds/current - Get current round
- POST /api/v1/rounds/{id}/answer - Submit answer
- POST /api/v1/rounds/{id}/vote - Submit vote
- POST /api/v1/rounds/{id}/voting - Start voting phase
- POST /api/v1/rounds/{id}/complete - Complete round

### WebSocket Events
- player.joined - Player joined lobby
- player.left - Player left game
- game.started - Game has started
- round.started - New round began
- answer.submitted - Answer count updated
- voting.started - Voting phase began
- vote.submitted - Vote count updated
- round.completed - Round results available
- game.finished - Game over with final scores
