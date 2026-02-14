<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_with_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'New User',
            'nickname' => 'NewUser',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'player' => ['id', 'nickname', 'is_guest'],
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertFalse($response->json('player.is_guest'));
        $this->assertEquals('NewUser', $response->json('player.nickname'));
    }

    public function test_register_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_requires_minimum_password_length(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_prevents_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_prevents_duplicate_nickname(): void
    {
        User::factory()->create(['nickname' => 'TakenNick']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nickname' => 'TakenNick',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_register_creates_user_and_player(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'full@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Full User',
            'nickname' => 'FullUser',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'full@example.com', 'nickname' => 'FullUser']);
        $this->assertDatabaseHas('players', ['nickname' => 'FullUser', 'is_guest' => false]);
    }

    public function test_register_with_guest_token_converts_guest(): void
    {
        // Create guest first
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'GuestConvert',
        ]);
        $guestToken = $guestResponse->json('player.guest_token');
        $playerId = $guestResponse->json('player.id');

        // Register with guest token
        $response = $this->postJson('/api/v1/auth/register', [
            'guest_token' => $guestToken,
            'email' => 'converted@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertFalse($response->json('player.is_guest'));
        $this->assertEquals($playerId, $response->json('player.id'));

        // Player should no longer have a guest token
        $player = Player::find($playerId);
        $this->assertNull($player->guest_token);
        $this->assertFalse($player->is_guest);
    }

    public function test_register_defaults_nickname_from_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('john', $response->json('player.nickname'));
    }

    public function test_register_returns_api_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'token@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nickname' => 'TokenUser',
        ]);

        $this->assertNotEmpty($response->json('token'));
    }
}
