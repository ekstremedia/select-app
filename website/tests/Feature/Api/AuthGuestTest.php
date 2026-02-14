<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_guest_with_valid_nickname(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ValidName',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'player' => ['id', 'nickname', 'guest_token', 'is_guest'],
            ]);

        $this->assertTrue($response->json('player.is_guest'));
        $this->assertEquals('ValidName', $response->json('player.nickname'));
    }

    public function test_guest_token_is_64_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'TokenTest',
        ]);

        $this->assertEquals(64, strlen($response->json('player.guest_token')));
    }

    public function test_nickname_must_be_at_least_3_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'AB',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_nickname_must_not_exceed_20_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ThisNicknameIsWayTooLong',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_nickname_only_allows_alphanumeric_and_underscores(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'bad name!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_nickname_allows_underscores(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'good_name_1',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('good_name_1', $response->json('player.nickname'));
    }

    public function test_nickname_is_required(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_guest_player_is_stored_in_database(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'DBCheck',
        ]);

        $playerId = $response->json('player.id');
        $player = Player::find($playerId);

        $this->assertNotNull($player);
        $this->assertEquals('DBCheck', $player->nickname);
        $this->assertTrue($player->is_guest);
        $this->assertNotNull($player->last_active_at);
    }

    public function test_guest_sets_last_active_at(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ActiveTest',
        ]);

        $player = Player::find($response->json('player.id'));
        $this->assertNotNull($player->last_active_at);
    }
}
