<?php

use App\Domain\Player\Actions\GetPlayerByTokenAction;
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

    // For guest users (via X-Guest-Token header)
    $guestToken = request()->header('X-Guest-Token');
    if ($guestToken) {
        $player = app(GetPlayerByTokenAction::class)->execute($guestToken);
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
