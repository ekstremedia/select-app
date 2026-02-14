<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Models\PlayerStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sortBy = $request->query('sort', 'games_won');
        $allowedSorts = ['games_won', 'games_played', 'win_rate', 'total_votes_received', 'rounds_won'];

        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'games_won';
        }

        $stats = PlayerStat::query()
            ->with('user:id,nickname')
            ->where('games_played', '>', 0)
            ->orderByDesc($sortBy)
            ->limit(50)
            ->get()
            ->map(fn ($stat, $index) => [
                'rank' => $index + 1,
                'nickname' => $stat->user?->nickname ?? 'Unknown',
                'games_played' => $stat->games_played,
                'games_won' => $stat->games_won,
                'win_rate' => $stat->win_rate,
                'rounds_played' => $stat->rounds_played,
                'rounds_won' => $stat->rounds_won,
                'total_votes_received' => $stat->total_votes_received,
                'total_sentences_submitted' => $stat->total_sentences_submitted,
                'best_sentence' => $stat->best_sentence,
                'best_sentence_votes' => $stat->best_sentence_votes,
            ]);

        return response()->json(['leaderboard' => $stats]);
    }
}
