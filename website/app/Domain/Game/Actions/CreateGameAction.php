<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Support\Str;

class CreateGameAction
{
    public function execute(Player $host, array $settings = []): Game
    {
        $game = new Game();
        $defaultSettings = $game->getDefaultSettings();
        $mergedSettings = array_merge($defaultSettings, $settings);

        $game = Game::create([
            'code' => $this->generateUniqueCode(),
            'host_player_id' => $host->id,
            'status' => Game::STATUS_LOBBY,
            'settings' => $mergedSettings,
            'total_rounds' => $mergedSettings['rounds'] ?? 5,
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
        do {
            $code = strtoupper(Str::random(6));
            // Avoid confusing characters
            $code = str_replace(['0', 'O', 'I', '1', 'L'], ['A', 'B', 'C', 'D', 'E'], $code);
        } while (Game::where('code', $code)->whereIn('status', [Game::STATUS_LOBBY, Game::STATUS_PLAYING])->exists());

        return $code;
    }
}
