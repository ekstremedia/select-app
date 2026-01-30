<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;
    private string $guestToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a guest player for tests
        $response = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'TestPlayer',
        ]);

        $this->player = Player::find($response->json('player.id'));
        $this->guestToken = $response->json('player.guest_token');
    }

    public function test_can_create_game(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'settings' => [
                'rounds' => 5,
                'answer_time' => 60,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'game' => [
                    'id',
                    'code',
                    'status',
                    'host_player_id',
                    'settings',
                    'players',
                ],
            ]);

        $this->assertEquals('lobby', $response->json('game.status'));
        $this->assertEquals($this->player->id, $response->json('game.host_player_id'));
        $this->assertCount(1, $response->json('game.players'));
    }

    public function test_game_code_is_6_characters(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $response->json('game.code');
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
    }

    public function test_can_get_game_by_code(): void
    {
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        $response = $this->getJson("/api/v1/games/{$code}");

        $response->assertStatus(200)
            ->assertJson([
                'game' => [
                    'code' => $code,
                ],
            ]);
    }

    public function test_returns_404_for_invalid_code(): void
    {
        $response = $this->getJson('/api/v1/games/INVALID');

        $response->assertStatus(404);
    }

    public function test_another_player_can_join_game(): void
    {
        // Create game with first player
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        // Create second player
        $player2Response = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'Player2',
        ]);
        $player2Token = $player2Response->json('player.guest_token');

        // Join game
        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('game.players'));
    }

    public function test_cannot_join_full_game(): void
    {
        // Create game with max 2 players
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'settings' => ['max_players' => 2],
        ]);

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['display_name' => 'P2']);
        $this->withHeaders(['X-Guest-Token' => $player2Response->json('player.guest_token')])
            ->postJson("/api/v1/games/{$code}/join");

        // Try to add third player
        $player3Response = $this->postJson('/api/v1/auth/guest', ['display_name' => 'P3']);
        $response = $this->withHeaders(['X-Guest-Token' => $player3Response->json('player.guest_token')])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Game is full']);
    }

    public function test_player_can_leave_game(): void
    {
        // Create game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['display_name' => 'P2']);
        $player2Token = $player2Response->json('player.guest_token');
        $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        // Second player leaves
        $response = $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/leave");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_host_can_start_game_with_enough_players(): void
    {
        // Create game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['display_name' => 'P2']);
        $this->withHeaders(['X-Guest-Token' => $player2Response->json('player.guest_token')])
            ->postJson("/api/v1/games/{$code}/join");

        // Start game
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$code}/start");

        $response->assertStatus(200)
            ->assertJson(['game' => ['status' => 'playing']])
            ->assertJsonStructure(['round' => ['id', 'acronym', 'round_number']]);
    }

    public function test_non_host_cannot_start_game(): void
    {
        // Create game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        // Add second player
        $player2Response = $this->postJson('/api/v1/auth/guest', ['display_name' => 'P2']);
        $player2Token = $player2Response->json('player.guest_token');
        $this->withHeaders(['X-Guest-Token' => $player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        // Non-host tries to start
        $response = $this->withHeaders([
            'X-Guest-Token' => $player2Token,
        ])->postJson("/api/v1/games/{$code}/start");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Only the host can start the game']);
    }

    public function test_cannot_start_game_without_enough_players(): void
    {
        // Create game (only host)
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $code = $createResponse->json('game.code');

        // Try to start
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$code}/start");

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'Need at least 2 players to start']);
    }
}
