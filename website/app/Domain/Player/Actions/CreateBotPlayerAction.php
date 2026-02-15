<?php

declare(strict_types=1);

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;
use Illuminate\Support\Str;

class CreateBotPlayerAction
{
    private const BOT_NAMES = [
        'Botulf', 'Bottolf', 'Botilda', 'ByteBot', 'NorBot',
        'Dansen', 'Fjansen', 'Gulansen', 'Spransen', 'Kransen',
        'Sansen', 'Bjansen', 'Kansen', 'Gransen', 'Pransen',
    ];

    public function execute(?string $nickname = null): Player
    {
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $nick = $nickname ?? $this->generateBotName();

            if (! Player::where('nickname', $nick)->exists()) {
                return Player::create([
                    'guest_token' => Str::random(64),
                    'nickname' => $nick,
                    'is_guest' => true,
                    'is_bot' => true,
                    'last_active_at' => now(),
                ]);
            }

            // If a specific nickname was passed, don't retry with a different one
            if ($nickname) {
                break;
            }
        }

        // Last resort: use a UUID suffix to guarantee uniqueness
        return Player::create([
            'guest_token' => Str::random(64),
            'nickname' => ($nickname ?? 'Bot').Str::random(4),
            'is_guest' => true,
            'is_bot' => true,
            'last_active_at' => now(),
        ]);
    }

    private function generateBotName(): string
    {
        $name = self::BOT_NAMES[array_rand(self::BOT_NAMES)];
        $suffix = random_int(10, 99);

        return "{$name}{$suffix}";
    }
}
