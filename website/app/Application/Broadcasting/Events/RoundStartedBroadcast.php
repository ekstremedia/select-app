<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundStartedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public Round $round
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'round.started';
    }

    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'acronym' => $this->round->acronym,
            'deadline' => $this->round->answer_deadline?->toIso8601String(),
        ];
    }
}
