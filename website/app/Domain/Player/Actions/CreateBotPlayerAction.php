<?php

declare(strict_types=1);

namespace App\Domain\Player\Actions;

use App\Infrastructure\Models\Player;
use Illuminate\Support\Str;

class CreateBotPlayerAction
{
    private const BOT_NAMES = [
        'Botulf', 'Bottolf', 'Botilda', 'ByteBot', 'NorBot',
        'Dansen', 'Fjansen', 'Gulansen', 'Spransen', 'Transen',
        'Sansen', 'Bjansen', 'Kansen', 'Gransen', 'Pransen',
    ];

    public function execute(?string $nickname = null): Player
    {
        $nickname = $nickname ?? $this->generateBotName();

        return Player::create([
            'guest_token' => Str::random(64),
            'nickname' => $nickname,
            'is_guest' => true,
            'is_bot' => true,
            'last_active_at' => now(),
        ]);
    }

    private function generateBotName(): string
    {
        $name = self::BOT_NAMES[array_rand(self::BOT_NAMES)];
        $suffix = rand(10, 99);

        return "{$name}{$suffix}";
    }
}
