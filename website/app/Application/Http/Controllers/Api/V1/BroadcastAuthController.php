<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Domain\Player\Actions\GetPlayerByTokenAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request, GetPlayerByTokenAction $getPlayerByToken): JsonResponse
    {
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if (! $channelName || ! $socketId) {
            return response()->json(['error' => 'Missing channel_name or socket_id'], 400);
        }

        // Get player from guest token or authenticated user
        $player = $this->getPlayer($request, $getPlayerByToken);
        if (! $player) {
            return response()->json(['error' => 'Unauthenticated'], 403);
        }

        // Check if this is a presence-game channel
        if (preg_match('/^presence-game\.([A-Z0-9]+)$/', $channelName, $matches)) {
            $gameCode = $matches[1];
            $authData = $this->authorizeGameChannel($player, $gameCode, $socketId, $channelName);

            if ($authData) {
                return response()->json($authData);
            }

            return response()->json(['error' => 'Forbidden'], 403);
        }

        // For other channels, deny by default
        return response()->json(['error' => 'Unknown channel'], 403);
    }

    private function getPlayer(Request $request, GetPlayerByTokenAction $getPlayerByToken): ?Player
    {
        // Try guest token first
        $guestToken = $request->header('X-Guest-Token');
        if ($guestToken) {
            $player = $getPlayerByToken->execute($guestToken);
            if ($player) {
                return $player;
            }
        }

        // Try authenticated user (session-based)
        if ($request->user()) {
            return Player::where('user_id', $request->user()->id)->first();
        }

        // Try Bearer token (Sanctum API token) — needed because this is a web route
        $sanctumUser = Auth::guard('sanctum')->user();
        if ($sanctumUser) {
            return Player::where('user_id', $sanctumUser->id)->first();
        }

        return null;
    }

    private function authorizeGameChannel(Player $player, string $gameCode, string $socketId, string $channelName): ?array
    {
        $game = Game::where('code', $gameCode)->first();

        if (! $game) {
            return null;
        }

        // Check if player is in the game
        $isInGame = $game->activePlayers()->where('players.id', $player->id)->exists();

        if (! $isInGame) {
            return null;
        }

        // Generate Pusher auth signature for presence channel
        $userData = [
            'user_id' => $player->id,
            'user_info' => [
                'id' => $player->id,
                'name' => $player->nickname,
            ],
        ];

        $stringToSign = $socketId.':'.$channelName.':'.json_encode($userData);
        $signature = hash_hmac('sha256', $stringToSign, config('broadcasting.connections.reverb.secret'));

        return [
            'auth' => config('broadcasting.connections.reverb.key').':'.$signature,
            'channel_data' => json_encode($userData),
        ];
    }
}
