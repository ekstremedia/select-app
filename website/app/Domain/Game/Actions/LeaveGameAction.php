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

        // If host leaves, transfer host to a co-host or another player
        if ($game->host_player_id === $player->id) {
            // Prefer co-hosts first, then any active player
            $newHost = $game->gamePlayers()
                ->where('player_id', '!=', $player->id)
                ->where('is_active', true)
                ->orderByDesc('is_co_host')
                ->first();

            if ($newHost) {
                $game->update(['host_player_id' => $newHost->player_id]);
            } elseif ($game->isInLobby()) {
                // No other players in lobby, end the game
                $game->update(['status' => Game::STATUS_FINISHED]);
            }
            // Mid-game with no other players: Delectus will detect <= 1 active and end it
        }

        $gamePlayer->update(['is_active' => false]);
    }
}
