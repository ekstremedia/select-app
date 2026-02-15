<?php

declare(strict_types=1);

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\Player;

class KickPlayerAction
{
    public function execute(Game $game, Player $kicker, string $targetPlayerId): GamePlayer
    {
        if ($game->isFinished()) {
            throw new \InvalidArgumentException('Cannot kick players from a finished game');
        }

        if (! $game->isHostOrCoHost($kicker)) {
            throw new \InvalidArgumentException('Only the host or co-host can kick players');
        }

        if ($targetPlayerId === $game->host_player_id) {
            throw new \InvalidArgumentException('Cannot kick the host');
        }

        $gamePlayer = $game->gamePlayers()
            ->where('player_id', $targetPlayerId)
            ->where('is_active', true)
            ->first();

        if (! $gamePlayer) {
            throw new \InvalidArgumentException('Player not found in this game');
        }

        $gamePlayer->update([
            'is_active' => false,
            'is_co_host' => false,
            'kicked_by' => $kicker->id,
        ]);

        return $gamePlayer;
    }
}
