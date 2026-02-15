<?php

declare(strict_types=1);

namespace App\Domain\Delectus;

use App\Application\Broadcasting\Events\ChatMessageBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Domain\Game\Actions\EndGameAction;
use App\Domain\Round\Actions\CompleteRoundAction;
use App\Domain\Round\Actions\StartRoundAction;
use App\Domain\Round\Actions\StartVotingAction;
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
        // Find active round from eager-loaded rounds collection
        // (cannot use $game->currentRound as a property — it's not a proper relationship)
        $round = $game->rounds
            ->whereIn('status', ['answering', 'voting'])
            ->first();

        if (! $round) {
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
            // All rounds complete — wait for time_between_rounds before ending
            $timeBetweenRounds = $game->settings['time_between_rounds'] ?? 5;
            $lastCompleted = $game->rounds()
                ->where('status', 'completed')
                ->latest('updated_at')
                ->first();

            if ($lastCompleted && (int) $lastCompleted->updated_at->diffInSeconds(now()) < $timeBetweenRounds) {
                return; // Still showing results
            }

            Log::info('Delectus: Ending game', ['game_code' => $game->code]);
            $this->endGameAction->execute($game);

            return;
        }

        // Check if the last completed round had 0 answers — abandoned game
        if ($completedRounds > 0) {
            $lastCompleted = $game->rounds()
                ->where('status', 'completed')
                ->latest('round_number')
                ->first();

            if ($lastCompleted && $lastCompleted->answers()->count() === 0) {
                Log::info('Delectus: Last round had no answers, ending game', [
                    'game_code' => $game->code,
                    'round' => $lastCompleted->round_number,
                ]);
                $this->endGameAction->execute($game);

                return;
            }

            // Check time_between_rounds delay
            $timeBetweenRounds = $game->settings['time_between_rounds'] ?? 5;

            if ($timeBetweenRounds > 0 && $lastCompleted && (int) $lastCompleted->updated_at->diffInSeconds(now()) < $timeBetweenRounds) {
                return; // Still waiting between rounds
            }
        }

        // Check active players — end game if 0 or 1 left
        $activePlayers = $game->gamePlayers()->where('is_active', true)->count();
        if ($activePlayers <= 1) {
            Log::info('Delectus: Not enough active players, ending game', [
                'game_code' => $game->code,
                'active_players' => $activePlayers,
            ]);
            $this->endGameAction->execute($game);

            return;
        }

        // Start new round
        $roundNumber = $completedRounds + 1;
        $game->update(['current_round' => $roundNumber]);

        Log::info('Delectus: Starting round', [
            'game_code' => $game->code,
            'round' => $roundNumber,
        ]);
        $round = $this->startRoundAction->execute($game);

        try {
            broadcast(new RoundStartedBroadcast($game, $round));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: round.started', ['game' => $game->code, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Answering deadline passed - transition to voting, or extend if no answers.
     *
     * Grace period logic:
     * - 0 answers + grace_count 0: extend by 50% of answer_time, notify players
     * - 0 answers + grace_count 1: extend once more by 50%
     * - 0 answers + grace_count 2+: abandon the game
     * - 1+ answers: proceed to voting normally
     */
    protected function handleAnsweringDeadline(Game $game, Round $round): void
    {
        $answersCount = $round->answers()->count();

        if ($answersCount === 0) {
            $graceCount = $round->grace_count ?? 0;
            $maxGrace = 2;

            if ($graceCount < $maxGrace) {
                // Extend the deadline
                $answerTime = $game->settings['answer_time'] ?? 120;
                $extension = (int) ceil($answerTime * 0.5);

                $round->update([
                    'answer_deadline' => now()->addSeconds($extension),
                    'grace_count' => $graceCount + 1,
                ]);

                Log::info('Delectus: No answers, extending deadline', [
                    'game_code' => $game->code,
                    'round' => $round->round_number,
                    'grace_count' => $graceCount + 1,
                    'extension_seconds' => $extension,
                ]);

                // Notify players via chat
                try {
                    broadcast(new ChatMessageBroadcast(
                        $game,
                        'Delectus',
                        $graceCount === 0
                            ? 'Ingen svar ennå! Litt ekstra tid...'
                            : 'Fortsatt ingen svar... siste sjanse!',
                        true
                    ));
                } catch (\Throwable $e) {
                    // Ignore broadcast failures
                }

                return;
            }

            // Max grace exceeded — abandon the game
            Log::info('Delectus: No answers after grace periods, ending game', [
                'game_code' => $game->code,
                'round' => $round->round_number,
            ]);

            // Mark the round as completed (skipped) so it doesn't block
            $round->update(['status' => Round::STATUS_COMPLETED]);

            $this->endGameAction->execute($game);

            return;
        }

        // With only 1 answer, voting is impossible (can't vote for own answer).
        // Skip voting and auto-complete the round with 0 votes.
        if ($answersCount === 1) {
            Log::info('Delectus: Only 1 answer, skipping voting', [
                'game_code' => $game->code,
                'round' => $round->round_number,
            ]);

            $round->update(['status' => Round::STATUS_COMPLETED]);
            $game->update(['status' => Game::STATUS_PLAYING]);

            try {
                broadcast(new \App\Application\Broadcasting\Events\RoundCompletedBroadcast(
                    $game,
                    $this->completeRoundAction->getScoresWithoutVoting($round)
                ));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: round.completed', ['game' => $game->code, 'error' => $e->getMessage()]);
            }

            return;
        }

        Log::info('Delectus: Answer deadline passed, starting voting', [
            'game_code' => $game->code,
            'round' => $round->round_number,
            'answers_count' => $answersCount,
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
