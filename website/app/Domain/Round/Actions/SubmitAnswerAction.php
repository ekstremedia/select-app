<?php

namespace App\Domain\Round\Actions;

use App\Domain\Round\Services\AcronymValidator;
use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;

class SubmitAnswerAction
{
    public function __construct(
        private AcronymValidator $validator
    ) {}

    public function execute(Round $round, Player $player, string $text): Answer
    {
        if (!$round->isAnswering()) {
            throw new \InvalidArgumentException('Round is not accepting answers');
        }

        if ($round->answer_deadline && $round->answer_deadline->isPast()) {
            throw new \InvalidArgumentException('Answer deadline has passed');
        }

        // Check player is in the game
        $isInGame = $round->game->activePlayers()
            ->where('players.id', $player->id)
            ->exists();

        if (!$isInGame) {
            throw new \InvalidArgumentException('Player is not in this game');
        }

        // Validate answer matches acronym
        $validation = $this->validator->validate($text, $round->acronym);
        if (!$validation->isValid) {
            throw new \InvalidArgumentException($validation->error);
        }

        // Check for existing answer
        $existing = $round->getAnswerByPlayer($player->id);
        if ($existing) {
            $existing->update(['text' => trim($text)]);
            return $existing->fresh();
        }

        return Answer::create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'text' => trim($text),
        ]);
    }
}
