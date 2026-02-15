<?php

namespace App\Application\Jobs;

use App\Application\Broadcasting\Events\VotingStartedBroadcast;
use App\Domain\Round\Actions\StartVotingAction;
use App\Infrastructure\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAnswerDeadlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $roundId
    ) {}

    public function handle(StartVotingAction $action): void
    {
        $round = Round::find($this->roundId);

        if (! $round || ! $round->isAnswering()) {
            return;
        }

        // Start voting phase
        $round = $action->execute($round);

        $answers = $round->answers()->with('player')->get()->map(fn ($a) => [
            'id' => $a->id,
            'player_id' => $a->player_id,
            'player_name' => $a->player->nickname,
            'text' => $a->text,
        ]);

        try {
            broadcast(new VotingStartedBroadcast($round->game, $round, $answers->toArray()));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Broadcast failed: voting.started (job)', ['error' => $e->getMessage()]);
        }
    }
}
