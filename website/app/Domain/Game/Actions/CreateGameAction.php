<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateGameAction
{
    public function execute(Player $host, array $settings = [], bool $isPublic = false, ?string $password = null): Game
    {
        $game = new Game;
        $defaultSettings = $game->getDefaultSettings();
        $mergedSettings = array_merge($defaultSettings, $settings);

        $game = Game::create([
            'code' => $this->generateUniqueCode(),
            'host_player_id' => $host->id,
            'status' => Game::STATUS_LOBBY,
            'settings' => $mergedSettings,
            'total_rounds' => $mergedSettings['rounds'] ?? 5,
            'is_public' => $isPublic,
            'password' => $password ? Hash::make($password) : null,
        ]);

        // Add host as first player
        $game->gamePlayers()->create([
            'player_id' => $host->id,
            'joined_at' => now(),
        ]);

        return $game;
    }

    private function generateUniqueCode(): string
    {
        $attempts = 0;
        do {
            $code = strtoupper(Str::random(5));
            // Avoid confusing characters
            $code = str_replace(['0', 'O', 'I', '1', 'L'], ['A', 'B', 'C', 'D', 'E'], $code);
            $attempts++;
        } while (Game::where('code', $code)->exists() && $attempts < 50);

        if (Game::where('code', $code)->exists()) {
            throw new \RuntimeException('Unable to generate unique game code after 50 attempts');
        }

        return $code;
    }
}
