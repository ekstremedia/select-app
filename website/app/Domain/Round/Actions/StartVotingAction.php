<?php

declare(strict_types=1);

namespace App\Domain\Round\Actions;

use App\Application\Broadcasting\Events\VotingStartedBroadcast;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;
use Illuminate\Support\Facades\Log;

class StartVotingAction
{
    /**
     * Transition a round from answering to voting phase.
     * Called by Delectus when answer deadline passes.
     */
    public function execute(Round $round): Round
    {
        if (! $round->isAnswering()) {
            throw new \InvalidArgumentException('Round is not in answering phase');
        }

        $game = $round->game;
        $settings = $game->settings;
        $voteTime = $settings['vote_time'] ?? 30;

        $round->update([
            'status' => Round::STATUS_VOTING,
            'vote_deadline' => now()->addSeconds($voteTime),
        ]);

        $game->update([
            'status' => Game::STATUS_VOTING,
        ]);

        // Load answers for the broadcast (hide player_id for anonymous voting)
        $answers = $round->answers()
            ->get()
            ->map(fn ($answer) => [
                'id' => $answer->id,
                'text' => $answer->text,
            ])
            ->shuffle() // Randomize order
            ->values()
            ->toArray();

        Log::info('Voting started', [
            'game_code' => $game->code,
            'round' => $round->round_number,
            'answers_count' => count($answers),
            'vote_deadline' => $round->vote_deadline,
        ]);

        // Delectus handles deadline - no need for scheduled job
        broadcast(new VotingStartedBroadcast($game, $round->fresh(), $answers));

        return $round->fresh();
    }
}
