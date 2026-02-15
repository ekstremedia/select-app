<?php

namespace App\Domain\Round\Actions;

use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;

class MarkReadyAction
{
    /**
     * Mark/unmark a player's answer as ready.
     * When all active players are ready, set the answer deadline to now.
     */
    public function execute(Round $round, Player $player, bool $ready): Answer
    {
        if (! $round->isAnswering()) {
            throw new \InvalidArgumentException('Round is not in the answering phase');
        }

        $game = $round->game;

        if (! ($game->settings['allow_ready_check'] ?? true)) {
            throw new \InvalidArgumentException('Ready check is not enabled for this game');
        }

        $answer = $round->getAnswerByPlayer($player->id);

        if (! $answer) {
            throw new \InvalidArgumentException('Player has not submitted an answer');
        }

        $answer->update(['is_ready' => $ready]);

        // Check if all active players have submitted answers and all are ready
        if ($ready) {
            $this->checkAutoAdvance($round);
        }

        return $answer->fresh();
    }

    /**
     * Check if all active players are ready and advance the deadline if so.
     */
    public function checkAutoAdvance(Round $round): void
    {
        $game = $round->game;
        $activePlayers = $game->activePlayers()->count();
        $readyCount = $round->answers()->where('is_ready', true)->count();

        if ($readyCount >= $activePlayers && $activePlayers > 0) {
            $round->update(['answer_deadline' => now()]);
        }
    }
}
