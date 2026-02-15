<?php

namespace App\Application\Jobs;

use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Application\Broadcasting\Events\RoundCompletedBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Domain\Round\Actions\CompleteRoundAction;
use App\Infrastructure\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVoteDeadlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $roundId
    ) {}

    public function handle(CompleteRoundAction $action): void
    {
        $round = Round::find($this->roundId);

        if (! $round || ! $round->isVoting()) {
            return;
        }

        $result = $action->execute($round);

        try {
            broadcast(new RoundCompletedBroadcast($round->game, $result['round_results']));

            if ($result['game_finished']) {
                broadcast(new GameFinishedBroadcast($round->game, $result['final_scores']));
            } else {
                broadcast(new RoundStartedBroadcast($round->game, $result['next_round']));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Broadcast failed: round.completed (job)', ['error' => $e->getMessage()]);
        }
    }
}
