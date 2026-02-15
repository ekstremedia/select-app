<?php

declare(strict_types=1);

namespace App\Domain\Round\Actions;

use App\Application\Broadcasting\Events\RoundCompletedBroadcast;
use App\Domain\Game\Services\ScoringService;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;
use Illuminate\Support\Facades\Log;

class CompleteRoundAction
{
    public function __construct(
        private ScoringService $scoringService,
    ) {}

    /**
     * Complete a round: calculate scores and broadcast results.
     * Called by Delectus when voting deadline passes.
     *
     * Note: Does NOT start the next round - Delectus handles that.
     */
    public function execute(Round $round): array
    {
        if (! $round->isVoting()) {
            throw new \InvalidArgumentException('Round is not in voting phase');
        }

        $game = $round->game;

        // Calculate and apply scores
        $roundResults = $this->scoringService->calculateRoundScores($round);

        $round->update([
            'status' => Round::STATUS_COMPLETED,
        ]);

        // Update game status back to playing (Delectus will start next round or end game)
        $game->update([
            'status' => Game::STATUS_PLAYING,
        ]);

        Log::info('Round completed', [
            'game_code' => $game->code,
            'round' => $round->round_number,
            'results' => $roundResults,
        ]);

        try {
            broadcast(new RoundCompletedBroadcast($game, $roundResults));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Broadcast failed: round.completed', ['game' => $game->code, 'error' => $e->getMessage()]);
        }

        return [
            'round_results' => $roundResults,
            'game_finished' => false,
        ];
    }

    /**
     * Build results for a round that skipped voting (e.g. only 1 answer).
     * Returns the same format as ScoringService::calculateRoundScores().
     */
    public function getScoresWithoutVoting(Round $round): array
    {
        return $round->answers()->with('player')->get()->map(fn ($answer) => [
            'player_id' => $answer->player_id,
            'player_name' => $answer->author_nickname ?? $answer->player?->nickname ?? 'Unknown',
            'answer' => $answer->text,
            'votes' => 0,
            'points_earned' => 0,
            'voters' => [],
        ])->toArray();
    }
}
