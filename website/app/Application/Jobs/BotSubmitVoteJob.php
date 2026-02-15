<?php

namespace App\Application\Jobs;

use App\Application\Broadcasting\Events\VoteSubmittedBroadcast;
use App\Domain\Round\Actions\SubmitVoteAction;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;
use App\Infrastructure\Models\Vote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BotSubmitVoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $roundId,
        public string $playerId,
    ) {}

    public function handle(SubmitVoteAction $voteAction): void
    {
        $round = Round::find($this->roundId);
        $player = Player::find($this->playerId);

        if (! $round || ! $player || ! $round->isVoting()) {
            return;
        }

        // Pick a random answer that isn't the bot's own
        $answer = $round->answers()
            ->where('player_id', '!=', $player->id)
            ->inRandomOrder()
            ->first();

        if (! $answer) {
            return;
        }

        try {
            $voteAction->execute($round, $player, $answer);
        } catch (\Throwable $e) {
            Log::warning('Bot failed to submit vote', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Broadcast like a normal user — use event() for reliable dispatch from queue context
        try {
            $game = $round->game;
            $totalVoters = $game->activePlayers()->count();
            $uniqueVoters = Vote::whereHas('answer', fn ($q) => $q->where('round_id', $round->id))
                ->distinct('voter_id')
                ->count('voter_id');
            event(new VoteSubmittedBroadcast($game, $uniqueVoters, $totalVoters));
            Log::warning('Bot vote broadcast sent', [
                'game' => $game->code,
                'bot' => $player->nickname,
                'votes' => $uniqueVoters,
                'total' => $totalVoters,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bot vote broadcast failed', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
