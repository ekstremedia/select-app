# Delectus - The Game Orchestrator

> Named after the original IRC bot from #select on EFnet

Delectus is the automated game master that runs Select games. It watches all active games and handles state transitions automatically.

## What Delectus Does

1. **Monitors active games** - Polls every second for games needing attention
2. **Enforces deadlines** - Transitions phases when time runs out
3. **Manages rounds** - Starts new rounds, ends games when complete
4. **Broadcasts events** - Notifies all players via WebSocket

## Game State Machine

```
┌─────────────────────────────────────────────────────────────┐
│                        GAME STATES                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   [waiting] ──(host starts)──► [playing] ──► [finished]    │
│                                    │                        │
│                                    ▼                        │
│                              ┌──────────┐                   │
│                              │  ROUNDS  │                   │
│                              └──────────┘                   │
│                                    │                        │
│     ┌──────────────────────────────┼────────────────────┐   │
│     │                              ▼                    │   │
│     │   [answering] ──(deadline)──► [voting]           │   │
│     │        │                         │               │   │
│     │        │                         ▼               │   │
│     │        │              [completed] ◄──(deadline)  │   │
│     │        │                         │               │   │
│     │        │                         ▼               │   │
│     │        │              (next round or end game)   │   │
│     └────────┴─────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Running Delectus

### Via Docker (recommended)

Delectus runs as its own container in the Docker Compose setup:

```bash
docker compose up -d
# Delectus starts automatically as select-delectus
```

### Manually

```bash
php artisan delectus:run
```

### Options

```bash
# Custom tick interval (default: 1 second)
php artisan delectus:run --interval=500

# Verbose output
php artisan delectus:run -v
```

## Architecture

```
app/Domain/Delectus/
├── DelectusService.php    # Main orchestrator, finds games needing attention
├── GameProcessor.php      # Processes individual game state transitions
└── README.md              # This file

app/Console/Commands/
└── DelectusRunCommand.php # Artisan command (daemon entry point)
```

## How It Works

1. **DelectusRunCommand** starts the daemon loop
2. Every tick, **DelectusService::tick()** is called
3. Service queries for games where:
   - Status is `playing` AND
   - Current round is `answering` with passed deadline, OR
   - Current round is `voting` with passed deadline, OR
   - No current round exists (need to start one)
4. **GameProcessor** handles each game:
   - Answering deadline → Start voting phase
   - Voting deadline → Complete round, calculate scores
   - No round → Start next round or end game
5. Each transition broadcasts WebSocket events

## Testing

```bash
# Run Delectus tests
php artisan test --filter=Delectus

# Seed a test game
php artisan db:seed --class=GameTestSeeder
```

## Logging

Delectus logs all actions to the Laravel log:

```bash
# View logs
docker compose logs -f delectus

# Or in Laravel log
tail -f storage/logs/laravel.log | grep Delectus
```

## Configuration

Game timing is configured per-game in `settings` JSON:

```json
{
  "rounds": 5,
  "answer_time": 60,
  "voting_time": 30,
  "acronym_length": 5
}
```

Delectus respects these settings when calculating deadlines.
