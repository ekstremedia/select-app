<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Broadcasting\Events\GameStartedBroadcast;
use App\Application\Broadcasting\Events\PlayerJoinedBroadcast;
use App\Application\Broadcasting\Events\PlayerLeftBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Domain\Game\Actions\CreateGameAction;
use App\Domain\Game\Actions\GetGameByCodeAction;
use App\Domain\Game\Actions\JoinGameAction;
use App\Domain\Game\Actions\LeaveGameAction;
use App\Domain\Game\Actions\StartGameAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function store(Request $request, CreateGameAction $action): JsonResponse
    {
        $player = $this->getPlayer($request);

        $request->validate([
            'settings' => 'nullable|array',
            'settings.rounds' => 'nullable|integer|min:1|max:10',
            'settings.answer_time' => 'nullable|integer|min:30|max:180',
            'settings.vote_time' => 'nullable|integer|min:15|max:60',
            'settings.min_players' => 'nullable|integer|min:2|max:8',
            'settings.max_players' => 'nullable|integer|min:2|max:8',
        ]);

        $game = $action->execute($player, $request->settings ?? []);

        return response()->json([
            'game' => $this->formatGame($game),
        ], 201);
    }

    public function show(string $code, GetGameByCodeAction $action): JsonResponse
    {
        $game = $action->execute($code);

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        return response()->json([
            'game' => $this->formatGame($game),
        ]);
    }

    public function join(Request $request, string $code, GetGameByCodeAction $getGame, JoinGameAction $action): JsonResponse
    {
        $player = $this->getPlayer($request);
        $game = $getGame->execute($code);

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $action->execute($game, $player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Broadcast player joined
        broadcast(new PlayerJoinedBroadcast($game, $player))->toOthers();

        return response()->json([
            'game' => $this->formatGame($game->fresh()),
        ]);
    }

    public function leave(Request $request, string $code, GetGameByCodeAction $getGame, LeaveGameAction $action): JsonResponse
    {
        $player = $this->getPlayer($request);
        $game = $getGame->execute($code);

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $action->execute($game, $player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Broadcast player left
        broadcast(new PlayerLeftBroadcast($game, $player))->toOthers();

        return response()->json([
            'success' => true,
        ]);
    }

    public function start(Request $request, string $code, GetGameByCodeAction $getGame, StartGameAction $action): JsonResponse
    {
        $player = $this->getPlayer($request);
        $game = $getGame->execute($code);

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $game = $action->execute($game, $player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $round = $game->currentRoundModel();

        // Broadcast game started and round started
        broadcast(new GameStartedBroadcast($game));
        broadcast(new RoundStartedBroadcast($game, $round));

        return response()->json([
            'game' => $this->formatGame($game),
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'acronym' => $round->acronym,
                'status' => $round->status,
                'answer_deadline' => $round->answer_deadline?->toIso8601String(),
            ],
        ]);
    }

    private function getPlayer(Request $request): Player
    {
        $guestToken = $request->header('X-Guest-Token');
        if ($guestToken) {
            $player = Player::where('guest_token', $guestToken)->first();
            if ($player) {
                return $player;
            }
        }

        if ($request->user()) {
            $player = Player::where('user_id', $request->user()->id)->first();
            if ($player) {
                return $player;
            }
        }

        abort(401, 'Unauthenticated');
    }

    private function formatGame($game): array
    {
        $players = $game->activePlayers()->get()->map(fn($p) => [
            'id' => $p->id,
            'display_name' => $p->display_name,
            'score' => $p->pivot->score,
            'is_host' => $p->id === $game->host_player_id,
        ]);

        return [
            'id' => $game->id,
            'code' => $game->code,
            'status' => $game->status,
            'host_player_id' => $game->host_player_id,
            'current_round' => $game->current_round,
            'total_rounds' => $game->total_rounds,
            'settings' => $game->settings,
            'players' => $players,
        ];
    }
}
