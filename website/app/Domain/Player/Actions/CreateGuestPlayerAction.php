<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;
use Illuminate\Support\Str;

class CreateGuestPlayerAction
{
    public function execute(string $displayName): Player
    {
        $displayName = trim($displayName);

        if (strlen($displayName) < 2 || strlen($displayName) > 50) {
            throw new \InvalidArgumentException('Display name must be between 2 and 50 characters');
        }

        return Player::create([
            'guest_token' => $this->generateGuestToken(),
            'display_name' => $displayName,
        ]);
    }

    private function generateGuestToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Player::where('guest_token', $token)->exists());

        return $token;
    }
}
