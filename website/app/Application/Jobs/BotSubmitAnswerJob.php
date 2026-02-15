<?php

namespace App\Application\Jobs;

use App\Application\Broadcasting\Events\AnswerSubmittedBroadcast;
use App\Application\Broadcasting\Events\PlayerReadyBroadcast;
use App\Domain\Round\Actions\MarkReadyAction;
use App\Domain\Round\Actions\SubmitAnswerAction;
use App\Domain\Round\Services\BotAnswerService;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BotSubmitAnswerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $roundId,
        public string $playerId,
    ) {}

    public function handle(SubmitAnswerAction $submitAction, BotAnswerService $botAnswerService, MarkReadyAction $markReadyAction): void
    {
        $round = Round::find($this->roundId);
        $player = Player::find($this->playerId);

        if (! $round || ! $player || ! $round->isAnswering()) {
            return;
        }

        try {
            $text = $botAnswerService->findAnswer($round->acronym);
            $answer = $submitAction->execute($round, $player, $text);
        } catch (\Throwable $e) {
            Log::warning('Bot failed to submit answer', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $game = $round->game;
        $totalPlayers = $game->activePlayers()->count();

        // Broadcast like a normal user — use event() for reliable dispatch from queue context
        try {
            $answersCount = $round->answers()->count();
            event(new AnswerSubmittedBroadcast($game, $answersCount, $totalPlayers));
        } catch (\Throwable $e) {
            Log::error('Bot answer broadcast failed', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto-ready: bots are always satisfied with their answer
        if ($game->settings['allow_ready_check'] ?? true) {
            try {
                $answer->update(['is_ready' => true]);
                $markReadyAction->checkAutoAdvance($round->fresh());
                $readyCount = $round->answers()->where('is_ready', true)->count();
                event(new PlayerReadyBroadcast($game, $readyCount, $totalPlayers));
            } catch (\Throwable $e) {
                Log::warning('Bot failed to mark ready', [
                    'player_id' => $this->playerId,
                    'round_id' => $this->roundId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
