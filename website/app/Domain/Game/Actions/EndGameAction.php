<?php

declare(strict_types=1);

namespace App\Domain\Game\Actions;

use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Domain\Game\Services\ScoringService;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GameResult;
use App\Infrastructure\Models\HallOfFame;
use App\Infrastructure\Models\PlayerStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EndGameAction
{
    public function __construct(
        private ScoringService $scoringService
    ) {}

    public function execute(Game $game, ?string $reason = null): Game
    {
        $finishedAt = now();
        $durationSeconds = $game->started_at
            ? (int) $game->started_at->diffInSeconds($finishedAt)
            : null;

        $settings = $game->settings;
        if ($reason) {
            $settings['finished_reason'] = $reason;
        }

        $game->update([
            'status' => Game::STATUS_FINISHED,
            'finished_at' => $finishedAt,
            'duration_seconds' => $durationSeconds,
            'settings' => $settings,
        ]);

        // Update player stats on player model
        $this->scoringService->updatePlayerStats($game);

        // Calculate final standings
        $gamePlayers = $game->gamePlayers()
            ->with('player')
            ->orderByDesc('score')
            ->get();

        $topScore = $gamePlayers->first()?->score ?? 0;
        $tiedAtTop = $gamePlayers->where('score', $topScore)->count() > 1;

        $finalScores = $gamePlayers
            ->map(function ($gp, $index) use ($tiedAtTop) {
                return [
                    'player_id' => $gp->player_id,
                    'player_name' => $gp->player?->nickname ?? 'Unknown',
                    'score' => $gp->score,
                    'is_winner' => $index === 0 && ! $tiedAtTop,
                ];
            })
            ->toArray();

        $winner = (! $tiedAtTop && ! empty($finalScores)) ? $finalScores[0] : null;

        $winnerGp = $gamePlayers->first();

        // Save game result (denormalized summary)
        GameResult::create([
            'game_id' => $game->id,
            'winner_nickname' => $winner ? $winner['player_name'] : null,
            'winner_user_id' => $winner ? $winnerGp?->player?->user_id : null,
            'final_scores' => $finalScores,
            'rounds_played' => $game->rounds()->where('status', 'completed')->count(),
            'player_count' => count($finalScores),
            'duration_seconds' => $durationSeconds,
        ]);

        // Save hall of fame entries for all answers with votes
        $this->saveHallOfFameEntries($game);

        // Update materialized player stats
        $this->updatePlayerStats($game, $winner);

        Log::info('Game finished', [
            'game_code' => $game->code,
            'winner' => $winner ? $winner['player_name'] : 'No winner',
            'final_scores' => $finalScores,
        ]);

        // Clear archive cache so fresh data is served
        Cache::forget("archive:{$game->code}");

        try {
            broadcast(new GameFinishedBroadcast($game, $finalScores));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: game.finished', ['game' => $game->code, 'error' => $e->getMessage()]);
        }

        return $game->fresh();
    }

    private function saveHallOfFameEntries(Game $game): void
    {
        $rounds = $game->rounds()->with(['answers.votes', 'answers.player'])->get();

        foreach ($rounds as $round) {
            $roundWinner = $round->answers->sortByDesc('votes_count')->first();

            foreach ($round->answers as $answer) {
                if ($answer->votes_count <= 0) {
                    continue;
                }

                $voterNicknames = $answer->votes->map(fn ($v) => $v->voter_nickname)->filter()->values()->toArray();

                HallOfFame::create([
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'round_number' => $round->round_number,
                    'acronym' => $round->acronym,
                    'sentence' => $answer->text,
                    'author_nickname' => $answer->author_nickname ?? $answer->player?->nickname ?? 'Unknown',
                    'author_user_id' => $answer->player?->user_id,
                    'votes_count' => $answer->votes_count,
                    'voter_nicknames' => $voterNicknames,
                    'is_round_winner' => $roundWinner && $roundWinner->id === $answer->id,
                ]);
            }
        }
    }

    private function updatePlayerStats(Game $game, ?array $winner): void
    {
        $rounds = $game->rounds()->with(['answers.votes'])->get();

        foreach ($game->gamePlayers()->with('player.user')->get() as $gp) {
            $user = $gp->player?->user;
            if (! $user) {
                continue;
            }

            $stat = PlayerStat::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'games_played' => 0,
                    'games_won' => 0,
                    'rounds_played' => 0,
                    'rounds_won' => 0,
                    'total_votes_received' => 0,
                    'total_sentences_submitted' => 0,
                    'best_sentence' => null,
                    'best_sentence_votes' => 0,
                    'win_rate' => 0,
                ]
            );

            $stat->increment('games_played');

            if ($winner && $gp->player_id === $winner['player_id']) {
                $stat->increment('games_won');
            }

            // Count rounds stats for this player
            foreach ($rounds as $round) {
                $playerAnswer = $round->answers->where('player_id', $gp->player_id)->first();
                if ($playerAnswer) {
                    $stat->increment('rounds_played');
                    $stat->increment('total_sentences_submitted');
                    $stat->increment('total_votes_received', $playerAnswer->votes_count);

                    // Check if round winner
                    $roundWinner = $round->answers->sortByDesc('votes_count')->first();
                    if ($roundWinner && $roundWinner->player_id === $gp->player_id && $roundWinner->votes_count > 0) {
                        $stat->increment('rounds_won');
                    }

                    // Track best sentence
                    if ($playerAnswer->votes_count > $stat->best_sentence_votes) {
                        $stat->update([
                            'best_sentence' => $playerAnswer->text,
                            'best_sentence_votes' => $playerAnswer->votes_count,
                        ]);
                    }
                }
            }

            $stat->refresh();
            $stat->recalculateWinRate();
        }
    }
}
