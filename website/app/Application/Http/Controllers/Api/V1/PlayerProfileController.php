<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Models\GameResult;
use App\Infrastructure\Models\HallOfFame;
use App\Infrastructure\Models\PlayerStat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController extends Controller
{
    public function show(string $nickname): JsonResponse
    {
        $user = User::where('nickname', $nickname)->first();

        if (! $user) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $stat = PlayerStat::where('user_id', $user->id)->first();

        $recentWins = HallOfFame::where('author_user_id', $user->id)
            ->where('is_round_winner', true)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($entry) => [
                'acronym' => $entry->acronym,
                'sentence' => $entry->sentence,
                'votes_count' => $entry->votes_count,
                'game_code' => $entry->game_code,
                'played_at' => $entry->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'player' => [
                'nickname' => $user->nickname,
                'avatar_url' => $user->avatar_url,
                'member_since' => $user->created_at?->toIso8601String(),
            ],
            'stats' => $stat ? [
                'games_played' => $stat->games_played,
                'games_won' => $stat->games_won,
                'win_rate' => $stat->win_rate,
                'rounds_played' => $stat->rounds_played,
                'rounds_won' => $stat->rounds_won,
                'votes_received' => $stat->total_votes_received,
                'total_sentences_submitted' => $stat->total_sentences_submitted,
                'best_sentence' => $stat->best_sentence,
                'best_sentence_votes' => $stat->best_sentence_votes,
            ] : null,
            'recent_wins' => $recentWins,
        ]);
    }

    public function stats(string $nickname): JsonResponse
    {
        $user = User::where('nickname', $nickname)->first();

        if (! $user) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $stat = PlayerStat::where('user_id', $user->id)->first();

        return response()->json([
            'stats' => $stat ? [
                'games_played' => $stat->games_played,
                'games_won' => $stat->games_won,
                'win_rate' => $stat->win_rate,
                'rounds_played' => $stat->rounds_played,
                'rounds_won' => $stat->rounds_won,
                'votes_received' => $stat->total_votes_received,
                'total_sentences_submitted' => $stat->total_sentences_submitted,
                'best_sentence' => $stat->best_sentence,
                'best_sentence_votes' => $stat->best_sentence_votes,
            ] : null,
        ]);
    }

    public function sentences(string $nickname, Request $request): JsonResponse
    {
        $user = User::where('nickname', $nickname)->first();

        if (! $user) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $limit = min((int) $request->query('limit', 20), 50);

        $sentences = HallOfFame::where('author_user_id', $user->id)
            ->orderByDesc('votes_count')
            ->limit($limit)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'acronym' => $entry->acronym,
                'text' => $entry->sentence,
                'votes_count' => $entry->votes_count,
                'game_code' => $entry->game_code,
                'is_round_winner' => $entry->is_round_winner,
                'played_at' => $entry->created_at?->toIso8601String(),
            ]);

        return response()->json(['sentences' => $sentences]);
    }

    public function games(string $nickname, Request $request): JsonResponse
    {
        $user = User::where('nickname', $nickname)->first();

        if (! $user) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $limit = min((int) $request->query('limit', 20), 50);

        $games = GameResult::query()
            ->with('game:id,code,finished_at')
            ->whereJsonContains('final_scores', [['player_name' => $user->nickname]])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($result) use ($user) {
                $playerScore = collect($result->final_scores)
                    ->firstWhere('player_name', $user->nickname);

                $rank = collect($result->final_scores)
                    ->sortByDesc('score')
                    ->values()
                    ->search(fn ($s) => $s['player_name'] === $user->nickname);

                return [
                    'code' => $result->game?->code,
                    'score' => $playerScore['score'] ?? 0,
                    'is_winner' => $playerScore['is_winner'] ?? false,
                    'placement' => $rank !== false ? '#'.($rank + 1) : null,
                    'player_count' => $result->player_count,
                    'finished_at' => $result->game?->finished_at?->toIso8601String(),
                ];
            });

        return response()->json(['games' => $games]);
    }
}
