<?php

namespace App\Domain\Game\Actions;

use App\Domain\Round\Actions\StartRoundAction;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;

class StartGameAction
{
    public function __construct(
        private StartRoundAction $startRoundAction
    ) {}

    public function execute(Game $game, Player $player): Game
    {
        if (! $game->isHostOrCoHost($player)) {
            throw new \InvalidArgumentException('Only the host or co-host can start the game');
        }

        if (! $game->isInLobby()) {
            throw new \InvalidArgumentException('Game has already started');
        }

        $minPlayers = $game->settings['min_players'] ?? 2;
        $playerCount = $game->activePlayers()->count();

        if ($playerCount < $minPlayers) {
            throw new \InvalidArgumentException("Need at least {$minPlayers} players to start");
        }

        $game->update([
            'status' => Game::STATUS_PLAYING,
            'current_round' => 1,
            'started_at' => now(),
        ]);

        // Start the first round
        $this->startRoundAction->execute($game);

        return $game->fresh();
    }
}
