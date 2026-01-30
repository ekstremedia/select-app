<?php

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('game.{code}', function ($user, string $code) {
    $game = Game::where('code', $code)->first();

    if (!$game) {
        return false;
    }

    // For authenticated users
    if ($user) {
        $player = Player::where('user_id', $user->id)->first();
        if ($player) {
            $isInGame = $game->activePlayers()->where('players.id', $player->id)->exists();
            if ($isInGame) {
                return [
                    'id' => $player->id,
                    'name' => $player->display_name,
                ];
            }
        }
    }

    return false;
});

// Guest channel authorization is handled via API
