<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Http\Requests\Api\V1\BanPlayerRequest;
use App\Domain\Player\Actions\BanPlayerAction;
use App\Domain\Player\Actions\UnbanPlayerAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function players(Request $request): JsonResponse
    {
        $query = Player::query()->with('user');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nickname', 'like', "%{$search}%");
        }

        $players = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($players);
    }

    public function games(Request $request): JsonResponse
    {
        $query = Game::query()->with('host');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $games = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($games);
    }

    public function ban(BanPlayerRequest $request, BanPlayerAction $action): JsonResponse
    {
        $validated = $request->validated();

        $player = Player::findOrFail($validated['player_id']);

        $action->execute(
            $player,
            $request->user(),
            $validated['reason'],
            ! empty($validated['ban_ip']) ? $request->ip() : null,
        );

        return response()->json(['message' => 'Player has been banned.']);
    }

    public function unban(string $playerId, UnbanPlayerAction $action): JsonResponse
    {
        $player = Player::findOrFail($playerId);

        $action->execute($player);

        return response()->json(['message' => 'Player has been unbanned.']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_players' => Player::count(),
            'total_games' => Game::count(),
            'active_today' => Player::where('last_active_at', '>=', now()->startOfDay())->count(),
            'games_today' => Game::where('created_at', '>=', now()->startOfDay())->count(),
            'games_finished' => Game::where('status', 'finished')->count(),
            'total_answers' => Answer::count(),
            'banned_players' => Player::whereHas('user', fn ($q) => $q->where('is_banned', true))->count(),
        ]);
    }
}
