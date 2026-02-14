<?php

namespace App\Domain\Round\Actions;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Domain\Round\Services\AcronymGenerator;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;

class StartRoundAction
{
    public function __construct(
        private AcronymGenerator $acronymGenerator
    ) {}

    public function execute(Game $game): Round
    {
        $settings = $game->settings;
        $answerTime = $settings['answer_time'] ?? 60;
        $minLength = $settings['acronym_length_min'] ?? 3;
        $maxLength = $settings['acronym_length_max'] ?? 6;

        $excludedLetters = $settings['excluded_letters'] ?? '';
        if ($excludedLetters) {
            $this->acronymGenerator->setExcludedLetters($excludedLetters);
        }

        $acronym = $this->acronymGenerator->generate($minLength, $maxLength);

        $round = Round::create([
            'game_id' => $game->id,
            'round_number' => $game->current_round,
            'acronym' => $acronym,
            'status' => Round::STATUS_ANSWERING,
            'answer_deadline' => now()->addSeconds($answerTime),
        ]);

        // Schedule deadline processing
        ProcessAnswerDeadlineJob::dispatch($round->id)
            ->delay(now()->addSeconds($answerTime + 2));

        return $round;
    }
}
