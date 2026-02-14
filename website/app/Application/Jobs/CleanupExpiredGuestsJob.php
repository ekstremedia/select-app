<?php

namespace App\Application\Jobs;

use App\Infrastructure\Models\Player;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupExpiredGuestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Player::query()
            ->where('is_guest', true)
            ->where('last_active_at', '<', now()->subDay())
            ->whereDoesntHave('gamePlayers', function ($query) {
                $query->whereHas('game', function ($q) {
                    $q->whereIn('status', ['lobby', 'playing']);
                });
            })
            ->delete();
    }
}
