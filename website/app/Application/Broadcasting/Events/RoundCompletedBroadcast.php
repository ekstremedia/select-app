<?php

namespace App\Application\Broadcasting\Events;

use App\Infrastructure\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundCompletedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public array $results
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'round.completed';
    }

    public function broadcastWith(): array
    {
        $scores = $this->game->gamePlayers()
            ->with('player')
            ->orderByDesc('score')
            ->get()
            ->map(fn ($gp) => [
                'player_id' => $gp->player_id,
                'player_name' => $gp->player?->nickname ?? 'Unknown',
                'score' => $gp->score,
            ]);

        return [
            'results' => $this->results,
            'scores' => $scores,
            'time_between_rounds' => $this->game->settings['time_between_rounds'] ?? 10,
        ];
    }
}
