<?php

declare(strict_types=1);

namespace App\Domain\Delectus;

use App\Infrastructure\Models\Game;
use Illuminate\Support\Facades\Log;

/**
 * Delectus - The Game Orchestrator
 *
 * Named after the original IRC bot from #select on EFnet.
 * Delectus watches all active games and automatically:
 * - Transitions rounds from answering → voting when deadline passes
 * - Transitions rounds from voting → completed when deadline passes
 * - Starts the next round or ends the game
 * - Broadcasts all state changes via WebSocket
 */
class DelectusService
{
    public function __construct(
        private GameProcessor $gameProcessor
    ) {}

    /**
     * Process all games that need attention.
     * Called every tick (1 second) by the daemon.
     *
     * @return int Number of games processed
     */
    public function tick(): int
    {
        $games = $this->findGamesNeedingAttention();
        $processed = 0;

        foreach ($games as $game) {
            try {
                $this->gameProcessor->process($game);
                $processed++;
            } catch (\Throwable $e) {
                Log::error('Delectus: Error processing game', [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Process stale lobbies (check every tick, logic inside handles timing)
        $lobbyGames = $this->findStaleLobbies();
        foreach ($lobbyGames as $game) {
            try {
                $this->gameProcessor->processLobby($game);
                $processed++;
            } catch (\Throwable $e) {
                Log::error('Delectus: Error processing lobby', [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Find all games that need Delectus to take action.
     *
     * A game needs attention when:
     * 1. Status is 'playing' or 'voting' AND has a current round AND:
     *    - Round is 'answering' and answer_deadline has passed
     *    - Round is 'voting' and vote_deadline has passed
     * 2. Status is 'playing' AND no current round (needs to start one or end game)
     */
    protected function findGamesNeedingAttention()
    {
        return Game::whereIn('status', ['playing', 'voting'])
            ->with(['rounds' => fn ($q) => $q->whereIn('status', ['answering', 'voting'])])
            ->get()
            ->filter(function (Game $game) {
                // Find the active round from eager-loaded rounds
                $round = $game->rounds
                    ->whereIn('status', ['answering', 'voting'])
                    ->first();

                // No current round - need to start one
                if (! $round) {
                    return true;
                }

                // Answering phase deadline passed
                if ($round->status === 'answering' && $round->answer_deadline && $round->answer_deadline->isPast()) {
                    return true;
                }

                // Voting phase deadline passed
                if ($round->status === 'voting' && $round->vote_deadline && $round->vote_deadline->isPast()) {
                    return true;
                }

                return false;
            });
    }

    /**
     * Find lobby games that have been inactive for over 4 minutes.
     * (Warning is sent at 5 min, close at 6 min — we query with buffer.)
     */
    protected function findStaleLobbies()
    {
        return Game::where('status', 'lobby')
            ->where('updated_at', '<=', now()->subMinutes(4))
            ->get();
    }

    /**
     * Get status information about Delectus and active games.
     */
    public function getStatus(): array
    {
        $activeGames = Game::whereIn('status', ['playing', 'voting'])->count();
        $waitingGames = Game::where('status', 'lobby')->count();

        return [
            'active_games' => $activeGames,
            'waiting_games' => $waitingGames,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
