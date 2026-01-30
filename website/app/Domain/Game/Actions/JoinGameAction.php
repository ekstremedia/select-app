<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\Player;

class JoinGameAction
{
    public function execute(Game $game, Player $player): GamePlayer
    {
        if (!$game->isInLobby()) {
            throw new \InvalidArgumentException('Cannot join a game that has already started');
        }

        $maxPlayers = $game->settings['max_players'] ?? 8;
        $currentCount = $game->activePlayers()->count();

        if ($currentCount >= $maxPlayers) {
            throw new \InvalidArgumentException('Game is full');
        }

        $existing = GamePlayer::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                throw new \InvalidArgumentException('Player already in game');
            }
            // Rejoin
            $existing->update(['is_active' => true]);
            return $existing;
        }

        return $game->gamePlayers()->create([
            'player_id' => $player->id,
            'joined_at' => now(),
        ]);
    }
}
