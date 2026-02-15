<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JoinGameAction
{
    public function execute(Game $game, Player $player, ?string $password = null): GamePlayer
    {
        if ($game->isFinished()) {
            throw new \InvalidArgumentException('Cannot join a finished game');
        }

        // Check password for protected games
        if ($game->password && ! Hash::check($password ?? '', $game->password)) {
            throw new \InvalidArgumentException('Incorrect game password');
        }

        return DB::transaction(function () use ($game, $player) {
            // Lock the game row to prevent race conditions on player count
            $game = Game::lockForUpdate()->find($game->id);

            if (! $game) {
                throw new \InvalidArgumentException('Game not found');
            }

            $existing = GamePlayer::where('game_id', $game->id)
                ->where('player_id', $player->id)
                ->first();

            if ($existing) {
                if ($existing->isBanned()) {
                    throw new \InvalidArgumentException('Du er utestengt fra dette spillet');
                }

                $maxPlayers = $game->settings['max_players'] ?? 8;
                $currentCount = $game->gamePlayers()->where('is_active', true)->count();

                if ($existing->isKicked()) {
                    if ($currentCount >= $maxPlayers) {
                        throw new \InvalidArgumentException('Game is full');
                    }
                    // Kicked but not banned — allow rejoin, clear kicked_by
                    $existing->update(['is_active' => true, 'kicked_by' => null]);

                    return $existing;
                }
                if ($existing->is_active) {
                    throw new \InvalidArgumentException('Player already in game');
                }
                if ($currentCount >= $maxPlayers) {
                    throw new \InvalidArgumentException('Game is full');
                }
                // Rejoin
                $existing->update(['is_active' => true]);

                return $existing;
            }

            $maxPlayers = $game->settings['max_players'] ?? 8;
            $currentCount = $game->gamePlayers()->where('is_active', true)->count();

            if ($currentCount >= $maxPlayers) {
                throw new \InvalidArgumentException('Game is full');
            }

            return $game->gamePlayers()->create([
                'player_id' => $player->id,
                'joined_at' => now(),
            ]);
        });
    }
}
