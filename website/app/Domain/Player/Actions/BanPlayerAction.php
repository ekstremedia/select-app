<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\BannedIp;
use App\Infrastructure\Models\Player;
use App\Models\User;

class BanPlayerAction
{
    public function execute(Player $player, User $bannedBy, string $reason, ?string $ipAddress = null): void
    {
        // Ban the user account if they have one
        if ($player->user) {
            $player->user->update([
                'is_banned' => true,
                'ban_reason' => $reason,
                'banned_at' => now(),
                'banned_by' => $bannedBy->id,
            ]);
        }

        // Optionally ban the IP address (for guests)
        if ($ipAddress) {
            BannedIp::updateOrCreate(
                ['ip_address' => $ipAddress],
                [
                    'reason' => $reason,
                    'banned_by' => $bannedBy->id,
                ],
            );
        }
    }
}
