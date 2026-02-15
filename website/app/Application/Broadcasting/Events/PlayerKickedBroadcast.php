<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerKickedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public Player $kickedPlayer,
        public string $kickedByNickname,
        public bool $banned = false,
        public ?string $banReason = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.kicked';
    }

    public function broadcastWith(): array
    {
        $game = $this->game->fresh();

        return [
            'player_id' => $this->kickedPlayer->id,
            'player_nickname' => $this->kickedPlayer->nickname,
            'kicked_by_nickname' => $this->kickedByNickname,
            'players_count' => $game->activePlayers()->count(),
            'banned' => $this->banned,
            'ban_reason' => $this->banReason,
        ];
    }
}
