<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameRematchBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $oldGame,
        public string $newGameCode
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->oldGame->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.rematch';
    }

    public function broadcastWith(): array
    {
        return [
            'new_game_code' => $this->newGameCode,
        ];
    }
}
