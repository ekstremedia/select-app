<?php

declare(strict_types=1);

namespace App\Domain\Delectus;

use App\Application\Broadcasting\Events\ChatMessageBroadcast;
use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Application\Broadcasting\Events\LobbyExpiringBroadcast;
use App\Application\Broadcasting\Events\RoundCompletedBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Application\Jobs\BotSubmitAnswerJob;
use App\Application\Jobs\BotSubmitVoteJob;
use App\Domain\Game\Actions\EndGameAction;
use App\Domain\Round\Actions\CompleteRoundAction;
use App\Domain\Round\Actions\StartRoundAction;
use App\Domain\Round\Actions\StartVotingAction;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;
use Illuminate\Support\Facades\DB;
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

    private const LOBBY_TIMEOUT_SECONDS = 300; // 5 minutes

    private const LOBBY_WARNING_SECONDS = 60; // 60 second warning before close

    /**
     * Process an inactive lobby game.
     *
     * Flow:
     * 1. Inactive > 5 min with no warning → broadcast warning, store timestamp
     * 2. Warning sent > 60s ago and no keepalive → close lobby
     * 3. If updated_at is newer than warning → user did keepalive, clear warning
     */
    public function processLobby(Game $game): void
    {
        $settings = $game->settings;
        $warningAt = $settings['lobby_warning_at'] ?? null;

        if ($warningAt) {
            $warningTime = \Carbon\Carbon::parse($warningAt);

            // User did keepalive after warning (updated_at is newer)
            if ($game->updated_at->gt($warningTime)) {
                unset($settings['lobby_warning_at']);
                DB::table('games')->where('id', $game->id)->update(['settings' => json_encode($settings)]);

                return;
            }

            // Warning expired — close the lobby
            if ($warningTime->diffInSeconds(now()) >= self::LOBBY_WARNING_SECONDS) {
                Log::info('Delectus: Closing inactive lobby', ['game_code' => $game->code]);

                $game->update([
                    'status' => Game::STATUS_FINISHED,
                    'finished_at' => now(),
                    'settings' => array_merge($settings, ['finished_reason' => 'lobby_timeout']),
                ]);

                try {
                    broadcast(new ChatMessageBroadcast(
                        $game,
                        'Delectus',
                        'Lobbyen ble stengt på grunn av inaktivitet.',
                        true
                    ));
                } catch (\Throwable $e) {
                    Log::error('Broadcast failed: chat.lobby_closed', ['game' => $game->code, 'error' => $e->getMessage()]);
                }

                try {
                    broadcast(new GameFinishedBroadcast($game, []));
                } catch (\Throwable $e) {
                    Log::error('Broadcast failed: game.finished', ['game' => $game->code, 'error' => $e->getMessage()]);
                }

                return;
            }

            // Still waiting for keepalive, do nothing
            return;
        }

        // No warning sent yet — check if idle long enough
        if ($game->updated_at->diffInSeconds(now()) >= self::LOBBY_TIMEOUT_SECONDS) {
            Log::info('Delectus: Lobby inactive, sending warning', ['game_code' => $game->code]);

            $settings['lobby_warning_at'] = now()->toIso8601String();

            // Update settings without touching updated_at (raw query)
            DB::table('games')->where('id', $game->id)->update([
                'settings' => json_encode($settings),
            ]);

            try {
                broadcast(new LobbyExpiringBroadcast($game, self::LOBBY_WARNING_SECONDS));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: lobby.expiring', ['game' => $game->code, 'error' => $e->getMessage()]);
            }

            try {
                broadcast(new ChatMessageBroadcast(
                    $game,
                    'Delectus',
                    'Lobbyen stenges om 60 sekunder på grunn av inaktivitet. Trykk "Bli" for å holde den åpen.',
                    true
                ));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: chat.lobby_warning', ['game' => $game->code, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * No current round - start the first round or a new round.
     */
    protected function handleNoCurrentRound(Game $game): void
    {
        $completedRounds = $game->rounds()->where('status', 'completed')->count();
        $totalRounds = $game->settings['rounds'] ?? 5;
        $timeBetweenRounds = $game->settings['time_between_rounds'] ?? 15;

        $lastCompleted = $completedRounds > 0
            ? $game->rounds()->where('status', 'completed')->latest('updated_at')->first()
            : null;

        if ($completedRounds >= $totalRounds) {
            // All rounds complete — wait for time_between_rounds before ending
            if ($lastCompleted && (int) $lastCompleted->updated_at->diffInSeconds(now()) < $timeBetweenRounds) {
                return; // Still showing results
            }

            Log::info('Delectus: Ending game', ['game_code' => $game->code]);
            $this->endGameAction->execute($game);

            return;
        }

        // Check if the last completed round had 0 answers — abandoned game
        if ($lastCompleted && $lastCompleted->answers()->count() === 0) {
            Log::info('Delectus: Last round had no answers, ending game', [
                'game_code' => $game->code,
                'round' => $lastCompleted->round_number,
            ]);

            try {
                broadcast(new ChatMessageBroadcast(
                    $game,
                    'Delectus',
                    'Spillet ble avsluttet på grunn av manglende deltakelse.',
                    true
                ));
            } catch (\Throwable $e) {
                // Ignore broadcast failures
            }

            $this->endGameAction->execute($game, 'inactivity');

            return;
        }

        // Check time_between_rounds delay
        if ($lastCompleted && $timeBetweenRounds > 0 && (int) $lastCompleted->updated_at->diffInSeconds(now()) < $timeBetweenRounds) {
            return; // Still waiting between rounds
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

        // Dispatch bot answer jobs with random delays
        $this->dispatchBotAnswers($game, $round);
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

            try {
                broadcast(new ChatMessageBroadcast(
                    $game,
                    'Delectus',
                    'Spillet ble avsluttet på grunn av manglende deltakelse.',
                    true
                ));
            } catch (\Throwable $e) {
                // Ignore broadcast failures
            }

            $this->endGameAction->execute($game, 'inactivity');

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
                broadcast(new RoundCompletedBroadcast(
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

        // Dispatch bot vote jobs with random delays
        $this->dispatchBotVotes($game, $round);
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

    /**
     * Dispatch delayed answer jobs for all bot players in the game.
     */
    private function dispatchBotAnswers(Game $game, Round $round): void
    {
        $botPlayers = $game->activePlayers()->where('players.is_bot', true)->get();
        $answerTime = $game->settings['answer_time'] ?? 60;
        // Bots answer between 20%-80% of answer time, capped to stay within deadline
        $maxDelay = min(max(8, (int) ($answerTime * 0.8)), max(1, $answerTime - 1));
        $minDelay = min(max(3, (int) ($answerTime * 0.2)), $maxDelay);

        foreach ($botPlayers as $bot) {
            $delay = random_int($minDelay, $maxDelay);
            BotSubmitAnswerJob::dispatch($round->id, $bot->id)->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Dispatch delayed vote jobs for all bot players in the game.
     */
    private function dispatchBotVotes(Game $game, Round $round): void
    {
        $botPlayers = $game->activePlayers()->where('players.is_bot', true)->get();
        $voteTime = $game->settings['vote_time'] ?? 30;
        // Bots vote between 15%-70% of vote time, spread out
        $minDelay = max(2, (int) ($voteTime * 0.15));
        $maxDelay = max(5, (int) ($voteTime * 0.7));

        foreach ($botPlayers as $bot) {
            $delay = random_int($minDelay, $maxDelay);
            BotSubmitVoteJob::dispatch($round->id, $bot->id)->delay(now()->addSeconds($delay));
        }
    }
}
