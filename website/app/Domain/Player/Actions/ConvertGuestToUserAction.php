<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ConvertGuestToUserAction
{
    public function execute(
        Player $player,
        string $email,
        string $password,
        ?string $name = null,
        ?string $nickname = null,
    ): Player {
        if (! $player->isGuest()) {
            throw new \InvalidArgumentException('Player is already registered');
        }

        $user = User::create([
            'name' => $name ?? $player->nickname,
            'nickname' => $nickname ?? $player->nickname,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $player->update([
            'user_id' => $user->id,
            'guest_token' => null,
            'is_guest' => false,
        ]);

        return $player->fresh();
    }
}
