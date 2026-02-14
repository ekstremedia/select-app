<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteSubmittedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public int $votesCount,
        public int $totalVoters
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vote.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'votes_count' => $this->votesCount,
            'total_voters' => $this->totalVoters,
        ];
    }
}
