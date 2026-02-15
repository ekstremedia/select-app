<?php

namespace Tests\Unit\Domain;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Domain\Game\Actions\EndGameAction;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EndGameActionTest extends TestCase
{
    use RefreshDatabase;

    private Player $host;

    private Player $player2;

    private string $hostToken;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create host
        $hostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Host']);
        $this->host = Player::find($hostResponse->json('player.id'));
        $this->hostToken = $hostResponse->json('player.guest_token');

        // Create player 2
        $p2Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $this->player2 = Player::find($p2Response->json('player.id'));

        // Create and start a game
        $createResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['settings' => ['rounds' => 1]]);

        $code = $createResponse->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $p2Response->json('player.guest_token')])
            ->postJson("/api/v1/games/{$code}/join");

        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/start");

        $this->game = Game::find($startResponse->json('game.id'));
    }

    public function test_end_game_stores_inactivity_reason(): void
    {
        $action = app(EndGameAction::class);

        $game = $action->execute($this->game, 'inactivity');

        $this->assertEquals('finished', $game->status);
        $this->assertEquals('inactivity', $game->settings['finished_reason']);
    }

    public function test_end_game_without_reason_has_no_finished_reason(): void
    {
        $action = app(EndGameAction::class);

        $game = $action->execute($this->game);

        $this->assertEquals('finished', $game->status);
        $this->assertArrayNotHasKey('finished_reason', $game->settings);
    }

    public function test_end_game_creates_game_result_with_player_names(): void
    {
        $action = app(EndGameAction::class);

        $game = $action->execute($this->game);

        $this->assertEquals('finished', $game->status);

        $gameResult = $game->gameResult;
        $this->assertNotNull($gameResult);
        $this->assertNotEmpty($gameResult->final_scores);

        // All player names should be non-null (the null-safe ?? 'Unknown' code path
        // is verified by the code structure — here we ensure normal players get names)
        foreach ($gameResult->final_scores as $score) {
            $this->assertNotNull($score['player_name']);
            $this->assertNotEquals('Unknown', $score['player_name']);
        }
    }
}
