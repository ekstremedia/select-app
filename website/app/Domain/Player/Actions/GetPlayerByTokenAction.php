<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;

class GetPlayerByTokenAction
{
    public function execute(string $token): ?Player
    {
        return Player::where('guest_token', $token)->first();
    }
}
