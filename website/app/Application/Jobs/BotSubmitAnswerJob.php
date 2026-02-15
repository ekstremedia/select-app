<?php

namespace App\Application\Jobs;

use App\Application\Broadcasting\Events\AnswerSubmittedBroadcast;
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

    public function handle(SubmitAnswerAction $submitAction, BotAnswerService $botAnswerService): void
    {
        $round = Round::find($this->roundId);
        $player = Player::find($this->playerId);

        if (! $round || ! $player || ! $round->isAnswering()) {
            return;
        }

        try {
            $text = $botAnswerService->findAnswer($round->acronym);
            $submitAction->execute($round, $player, $text);
        } catch (\Throwable $e) {
            Log::warning('Bot failed to submit answer', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Broadcast like a normal user — use event() for reliable dispatch from queue context
        try {
            $game = $round->game;
            $answersCount = $round->answers()->count();
            $totalPlayers = $game->activePlayers()->count();
            event(new AnswerSubmittedBroadcast($game, $answersCount, $totalPlayers));
            Log::info('Bot answer broadcast sent', [
                'game' => $game->code,
                'bot' => $player->nickname,
                'answers' => $answersCount,
                'total' => $totalPlayers,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bot answer broadcast failed', [
                'player_id' => $this->playerId,
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
