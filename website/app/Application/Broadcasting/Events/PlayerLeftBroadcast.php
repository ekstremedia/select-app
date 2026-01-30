<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerLeftBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public Player $player
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.' . $this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.left';
    }

    public function broadcastWith(): array
    {
        $game = $this->game->fresh();
        return [
            'player_id' => $this->player->id,
            'players_count' => $game->activePlayers()->count(),
            'new_host_id' => $game->host_player_id,
        ];
    }
}
