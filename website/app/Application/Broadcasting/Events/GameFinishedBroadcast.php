<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameFinishedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public array $finalScores
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.' . $this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.finished';
    }

    public function broadcastWith(): array
    {
        $winner = collect($this->finalScores)->firstWhere('is_winner', true);

        return [
            'winner' => $winner,
            'final_scores' => $this->finalScores,
        ];
    }
}
