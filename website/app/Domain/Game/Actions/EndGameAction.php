<?php

declare(strict_types=1);

namespace App\Domain\Game\Actions;

use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Domain\Game\Services\ScoringService;
use App\Infrastructure\Models\Game;
use Illuminate\Support\Facades\Log;

class EndGameAction
{
    public function __construct(
        private ScoringService $scoringService
    ) {}

    /**
     * End a game and announce the winner.
     * Called by Delectus when all rounds are complete.
     */
    public function execute(Game $game): Game
    {
        $game->update([
            'status' => Game::STATUS_FINISHED,
        ]);

        // Update player stats
        $this->scoringService->updatePlayerStats($game);

        // Calculate final standings
        $finalScores = $game->gamePlayers()
            ->with('player')
            ->orderByDesc('score')
            ->get()
            ->map(function ($gp, $index) {
                return [
                    'player_id' => $gp->player_id,
                    'player_name' => $gp->player->display_name,
                    'score' => $gp->score,
                    'is_winner' => $index === 0,
                ];
            })
            ->toArray();

        $winner = $finalScores[0] ?? null;

        Log::info('Game finished', [
            'game_code' => $game->code,
            'winner' => $winner ? $winner['player_name'] : 'No winner',
            'final_scores' => $finalScores,
        ]);

        broadcast(new GameFinishedBroadcast($game, $finalScores));

        return $game->fresh();
    }
}
