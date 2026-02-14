<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GameCreationTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private string $guestToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent ProcessAnswerDeadlineJob from running synchronously in tests
        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create a guest player for tests
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'TestPlayer',
        ]);

        $this->player = Player::find($response->json('player.id'));
        $this->guestToken = $response->json('player.guest_token');
    }

    public function test_can_create_public_game(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'is_public' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('game.is_public', true);
    }

    public function test_can_create_password_protected_game(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'password' => 'test123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('game.has_password', true);

        // Ensure the raw password is not exposed in the response
        $this->assertArrayNotHasKey('password', $response->json('game'));
    }

    public function test_cannot_join_password_game_without_password(): void
    {
        // Create a password-protected game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'password' => 'test123',
        ]);

        $code = $createResponse->json('game.code');

        // Create second player
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        // Try joining without password
        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Incorrect game password']);
    }

    public function test_can_join_password_game_with_correct_password(): void
    {
        // Create a password-protected game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'password' => 'test123',
        ]);

        $code = $createResponse->json('game.code');

        // Create second player
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        // Join with correct password
        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->postJson("/api/v1/games/{$code}/join", [
            'password' => 'test123',
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('game.players'));
    }

    public function test_cannot_join_password_game_with_wrong_password(): void
    {
        // Create a password-protected game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'password' => 'test123',
        ]);

        $code = $createResponse->json('game.code');

        // Create second player
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        // Try joining with wrong password
        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->postJson("/api/v1/games/{$code}/join", [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Incorrect game password']);
    }

    public function test_public_games_appear_in_game_list(): void
    {
        // Create a public lobby game
        $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'is_public' => true,
        ]);

        // Create second player to fetch the list (games index requires player middleware)
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->getJson('/api/v1/games');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'games');

        $this->assertEquals('TestPlayer', $response->json('games.0.host_nickname'));
    }

    public function test_private_games_do_not_appear_in_game_list(): void
    {
        // Create a private game (is_public defaults to false)
        $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        // Create second player to fetch the list
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->getJson('/api/v1/games');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'games');
    }

    public function test_game_state_endpoint_returns_full_state(): void
    {
        // Create game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'settings' => ['answer_time' => 120, 'vote_time' => 60],
        ]);

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $player2Token = $player2Response->json('player.guest_token');

        $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        // Start the game
        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/start");

        // Get game state
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->getJson("/api/v1/games/{$code}/state");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'game' => [
                    'id',
                    'code',
                    'status',
                    'host_player_id',
                    'current_round',
                    'total_rounds',
                    'settings',
                    'is_public',
                    'has_password',
                    'players',
                ],
                'round' => [
                    'id',
                    'round_number',
                    'acronym',
                    'status',
                    'answer_deadline',
                ],
            ]);

        $this->assertEquals('playing', $response->json('game.status'));
        $this->assertEquals(1, $response->json('round.round_number'));
        $this->assertEquals('answering', $response->json('round.status'));
        $this->assertCount(2, $response->json('game.players'));
    }
}
