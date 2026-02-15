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
        if (! $round->isAnswering()) {
            throw new \InvalidArgumentException('Round is not accepting answers');
        }

        if ($round->answer_deadline && $round->answer_deadline->isPast()) {
            throw new \InvalidArgumentException('Answer deadline has passed');
        }

        // Check player is in the game
        $isInGame = $round->game->activePlayers()
            ->where('players.id', $player->id)
            ->exists();

        if (! $isInGame) {
            throw new \InvalidArgumentException('Player is not in this game');
        }

        // Normalise to lowercase (the game does not use uppercase)
        $text = mb_strtolower(trim($text));

        // Validate answer matches acronym
        $validation = $this->validator->validate($text, $round->acronym);
        if (! $validation->isValid) {
            throw new \InvalidArgumentException($validation->error);
        }

        // Check for existing answer
        $existing = $round->getAnswerByPlayer($player->id);
        if ($existing) {
            // Enforce max_edits (0 = unlimited)
            $maxEdits = $round->game->settings['max_edits'] ?? 0;
            if ($maxEdits > 0 && $existing->edit_count >= $maxEdits) {
                throw new \InvalidArgumentException('Maximum edits reached');
            }

            $existing->update([
                'text' => $text,
                'edit_count' => $existing->edit_count + 1,
                'is_ready' => false,
            ]);

            return $existing->fresh();
        }

        return Answer::create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'text' => $text,
            'author_nickname' => $player->nickname,
        ]);
    }
}
