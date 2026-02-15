<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MidGameJoinTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private string $guestToken;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'HostPlayer',
        ]);

        $this->player = Player::find($response->json('player.id'));
        $this->guestToken = $response->json('player.guest_token');
    }

    private function createStartedGame(): array
    {
        // Create game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'is_public' => true,
            'settings' => ['max_players' => 8],
        ]);

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $player2Token = $player2Response->json('player.guest_token');
        $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        // Start game
        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/start");

        return ['code' => $code, 'player2_token' => $player2Token];
    }

    public function test_player_can_join_started_game(): void
    {
        $game = $this->createStartedGame();
        $code = $game['code'];

        // Create third player and join mid-game
        $player3Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player3']);
        $player3Token = $player3Response->json('player.guest_token');

        $response = $this->withHeaders(['X-Guest-Token' => $player3Token])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('game.players'));
    }

    public function test_cannot_join_finished_game(): void
    {
        // Create game and manually set it to finished
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');
        Game::where('code', $code)->update(['status' => 'finished']);

        // Try to join
        $player2Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $player2Token = $player2Response->json('player.guest_token');

        $response = $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cannot join a finished game']);
    }

    public function test_game_list_includes_active_games(): void
    {
        $this->createStartedGame();

        // Fetch game list as another player
        $player3Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player3']);
        $player3Token = $player3Response->json('player.guest_token');

        $response = $this->withHeaders(['X-Guest-Token' => $player3Token])
            ->getJson('/api/v1/games');

        $response->assertStatus(200);

        $games = $response->json('games');
        $this->assertCount(1, $games);
        $this->assertEquals('playing', $games[0]['status']);
        $this->assertArrayHasKey('current_round', $games[0]);
        $this->assertArrayHasKey('total_rounds', $games[0]);
    }

    public function test_state_endpoint_returns_phase(): void
    {
        $game = $this->createStartedGame();
        $code = $game['code'];

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->getJson("/api/v1/games/{$code}/state");

        $response->assertStatus(200)
            ->assertJsonPath('phase', 'playing');
    }

    public function test_state_returns_lobby_phase_for_lobby_game(): void
    {
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->getJson("/api/v1/games/{$code}/state");

        $response->assertStatus(200)
            ->assertJsonPath('phase', 'lobby');
    }

    public function test_state_returns_finished_phase_for_finished_game(): void
    {
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');
        Game::where('code', $code)->update(['status' => 'finished']);

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->getJson("/api/v1/games/{$code}/state");

        $response->assertStatus(200)
            ->assertJsonPath('phase', 'finished');
    }
}
