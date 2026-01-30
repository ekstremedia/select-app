<?php

declare(strict_types=1);

namespace App\Domain\Delectus;

use App\Domain\Round\Actions\CompleteRoundAction;
use App\Domain\Round\Actions\StartRoundAction;
use App\Domain\Round\Actions\StartVotingAction;
use App\Domain\Game\Actions\EndGameAction;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;
use Illuminate\Support\Facades\Log;

/**
 * Processes individual game state transitions.
 *
 * State machine:
 *
 *   [waiting] → [playing] → [finished]
 *                   │
 *                   ▼
 *   Round: [answering] → [voting] → [completed]
 *                              │
 *                              ▼
 *                    Next round or game ends
 */
class GameProcessor
{
    public function __construct(
        private StartRoundAction $startRoundAction,
        private StartVotingAction $startVotingAction,
        private CompleteRoundAction $completeRoundAction,
        private EndGameAction $endGameAction,
    ) {}

    /**
     * Process a game that needs attention.
     */
    public function process(Game $game): void
    {
        $round = $game->currentRound;

        if (!$round) {
            $this->handleNoCurrentRound($game);
            return;
        }

        match ($round->status) {
            'answering' => $this->handleAnsweringDeadline($game, $round),
            'voting' => $this->handleVotingDeadline($game, $round),
            default => null,
        };
    }

    /**
     * No current round - start the first round or a new round.
     */
    protected function handleNoCurrentRound(Game $game): void
    {
        $completedRounds = $game->rounds()->where('status', 'completed')->count();
        $totalRounds = $game->settings['rounds'] ?? 5;

        if ($completedRounds >= $totalRounds) {
            // All rounds complete - end the game
            Log::info('Delectus: Ending game', ['game_code' => $game->code]);
            $this->endGameAction->execute($game);
        } else {
            // Start new round
            $roundNumber = $completedRounds + 1;
            Log::info('Delectus: Starting round', [
                'game_code' => $game->code,
                'round' => $roundNumber,
            ]);
            $this->startRoundAction->execute($game, $roundNumber);
        }
    }

    /**
     * Answering deadline passed - transition to voting.
     */
    protected function handleAnsweringDeadline(Game $game, Round $round): void
    {
        Log::info('Delectus: Answer deadline passed, starting voting', [
            'game_code' => $game->code,
            'round' => $round->round_number,
            'answers_count' => $round->answers()->count(),
        ]);

        $this->startVotingAction->execute($round);
    }

    /**
     * Voting deadline passed - complete round and prepare next.
     */
    protected function handleVotingDeadline(Game $game, Round $round): void
    {
        Log::info('Delectus: Voting deadline passed, completing round', [
            'game_code' => $game->code,
            'round' => $round->round_number,
            'votes_count' => $round->answers()->withCount('votes')->get()->sum('votes_count'),
        ]);

        $this->completeRoundAction->execute($round);

        // The next tick will start the new round or end the game
    }
}
