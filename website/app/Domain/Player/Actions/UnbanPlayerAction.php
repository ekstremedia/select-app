<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;

class UnbanPlayerAction
{
    public function execute(Player $player): void
    {
        if ($player->user) {
            $player->user->update([
                'is_banned' => false,
                'ban_reason' => null,
                'banned_at' => null,
                'banned_by' => null,
            ]);
        }
    }
}
