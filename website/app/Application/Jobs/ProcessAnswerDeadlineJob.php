<?php

namespace App\Application\Jobs;

use App\Domain\Round\Actions\StartVotingAction;
use App\Infrastructure\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

        // Start voting phase — StartVotingAction already broadcasts VotingStartedBroadcast
        try {
            $action->execute($round);
        } catch (\Throwable $e) {
            Log::error('ProcessAnswerDeadlineJob: Failed to start voting', ['error' => $e->getMessage()]);
        }
    }
}
