<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\Player;

class LeaveGameAction
{
    public function execute(Game $game, Player $player): void
    {
        $gamePlayer = GamePlayer::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->where('is_active', true)
            ->first();

        if (! $gamePlayer) {
            throw new \InvalidArgumentException('Player is not in this game');
        }

        // If host leaves during lobby, transfer host or end game
        if ($game->host_player_id === $player->id && $game->isInLobby()) {
            $newHost = $game->activePlayers()
                ->where('players.id', '!=', $player->id)
                ->first();

            if ($newHost) {
                $game->update(['host_player_id' => $newHost->id]);
            } else {
                // No other players, end the game
                $game->update(['status' => Game::STATUS_FINISHED]);
            }
        }

        $gamePlayer->update(['is_active' => false]);
    }
}
