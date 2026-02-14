<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GameResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GameResult::query()
            ->with('game:id,code,settings,finished_at')
            ->latest('created_at');

        if ($request->query('player')) {
            $query->where('winner_nickname', 'like', '%'.$request->query('player').'%');
        }

        if ($request->query('period') === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        } elseif ($request->query('period') === 'month') {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $games = $query->paginate(20);

        $games->getCollection()->transform(fn ($result) => [
            'id' => $result->id,
            'code' => $result->game?->code,
            'winner_nickname' => $result->winner_nickname,
            'rounds_played' => $result->rounds_played,
            'rounds_count' => $result->rounds_played,
            'player_count' => $result->player_count,
            'duration_seconds' => $result->duration_seconds,
            'final_scores' => $result->final_scores,
            'finished_at' => $result->game?->finished_at?->toIso8601String(),
            'played_at' => $result->created_at?->toIso8601String(),
        ]);

        return response()->json($games);
    }

    public function show(string $code): JsonResponse
    {
        $game = Game::where('code', $code)
            ->with([
                'rounds.answers.player',
                'rounds.answers.votes',
                'gamePlayers.player',
                'gameResult',
            ])
            ->firstOrFail();

        $rounds = $game->rounds->sortBy('round_number')->values()->map(fn ($round) => [
            'round_number' => $round->round_number,
            'acronym' => $round->acronym,
            'answers' => $round->answers->sortByDesc('votes_count')->values()->map(fn ($a) => [
                'player_name' => $a->author_nickname ?? $a->player->nickname,
                'text' => $a->text,
                'votes_count' => $a->votes_count,
                'voters' => $a->votes->map(fn ($v) => $v->voter_nickname)->filter()->values(),
            ]),
        ]);

        $players = $game->gamePlayers->sortByDesc('score')->values()->map(fn ($gp, $i) => [
            'nickname' => $gp->player->nickname,
            'score' => $gp->score,
            'rank' => $i + 1,
        ]);

        return response()->json([
            'game' => [
                'code' => $game->code,
                'status' => $game->status,
                'settings' => $game->settings,
                'started_at' => $game->started_at?->toIso8601String(),
                'finished_at' => $game->finished_at?->toIso8601String(),
                'duration_seconds' => $game->duration_seconds,
            ],
            'players' => $players,
            'rounds' => $rounds,
        ]);
    }
}
