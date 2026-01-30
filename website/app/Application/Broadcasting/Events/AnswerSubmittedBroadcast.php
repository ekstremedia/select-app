<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerSubmittedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public int $answersCount,
        public int $totalPlayers
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.' . $this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'answer.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'answers_count' => $this->answersCount,
            'total_players' => $this->totalPlayers,
        ];
    }
}
