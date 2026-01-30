<?php

namespace App\Domain\Game\Services;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\Round;

class ScoringService
{
    public function calculateRoundScores(Round $round): array
    {
        $results = [];
        $answers = $round->answers()->with('player', 'votes.voter')->get();

        foreach ($answers as $answer) {
            $points = $answer->votes_count;

            // Update game player score
            $gamePlayer = GamePlayer::where('game_id', $round->game_id)
                ->where('player_id', $answer->player_id)
                ->first();

            if ($gamePlayer) {
                $gamePlayer->increment('score', $points);
            }

            $results[] = [
                'player_id' => $answer->player_id,
                'player_name' => $answer->player->display_name,
                'answer' => $answer->text,
                'votes' => $answer->votes_count,
                'points_earned' => $points,
                'voters' => $answer->votes->map(fn($v) => [
                    'id' => $v->voter_id,
                    'name' => $v->voter->display_name,
                ])->toArray(),
            ];
        }

        // Sort by votes descending
        usort($results, fn($a, $b) => $b['votes'] - $a['votes']);

        return $results;
    }

    public function getFinalScores(Game $game): array
    {
        $gamePlayers = $game->gamePlayers()
            ->with('player')
            ->orderByDesc('score')
            ->get();

        $scores = [];
        $rank = 1;

        foreach ($gamePlayers as $gp) {
            $scores[] = [
                'rank' => $rank,
                'player_id' => $gp->player_id,
                'player_name' => $gp->player->display_name,
                'score' => $gp->score,
                'is_winner' => $rank === 1,
            ];
            $rank++;
        }

        return $scores;
    }

    public function updatePlayerStats(Game $game): void
    {
        $winner = $game->gamePlayers()
            ->orderByDesc('score')
            ->first();

        foreach ($game->gamePlayers as $gp) {
            $player = $gp->player;
            $player->increment('games_played');
            $player->increment('total_score', $gp->score);

            if ($winner && $gp->player_id === $winner->player_id) {
                $player->increment('games_won');
            }
        }
    }
}
