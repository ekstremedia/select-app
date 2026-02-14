<?php

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;
use Illuminate\Support\Str;

class CreateGuestPlayerAction
{
    public function execute(string $nickname): Player
    {
        $nickname = trim($nickname);

        if (strlen($nickname) < 3 || strlen($nickname) > 20) {
            throw new \InvalidArgumentException('Nickname must be between 3 and 20 characters');
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $nickname)) {
            throw new \InvalidArgumentException('Nickname may only contain letters, numbers, and underscores');
        }

        return Player::create([
            'guest_token' => $this->generateGuestToken(),
            'nickname' => $nickname,
            'is_guest' => true,
            'last_active_at' => now(),
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
