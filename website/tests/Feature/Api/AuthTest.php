<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_guest_player(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'TestPlayer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'player' => [
                    'id',
                    'display_name',
                    'guest_token',
                    'is_guest',
                ],
            ]);

        $this->assertTrue($response->json('player.is_guest'));
        $this->assertEquals('TestPlayer', $response->json('player.display_name'));
        $this->assertNotNull($response->json('player.guest_token'));
    }

    public function test_guest_requires_display_name(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['display_name']);
    }

    public function test_guest_display_name_min_length(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['display_name']);
    }

    public function test_can_register_new_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'player' => ['id', 'display_name', 'is_guest'],
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertFalse($response->json('player.is_guest'));
        $this->assertEquals('Test User', $response->json('user.name'));
    }

    public function test_can_convert_guest_to_user(): void
    {
        // Create a guest first
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'GuestPlayer',
        ]);

        $guestToken = $guestResponse->json('player.guest_token');

        // Convert to user
        $response = $this->postJson('/api/v1/auth/convert', [
            'guest_token' => $guestToken,
            'email' => 'converted@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'player' => ['id', 'display_name', 'is_guest'],
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertFalse($response->json('player.is_guest'));
    }

    public function test_can_login_with_credentials(): void
    {
        // Create user first
        $user = User::create([
            'name' => 'Test User',
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        Player::create([
            'user_id' => $user->id,
            'display_name' => 'Test User',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'player',
                'user',
                'token',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'wrong@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_get_me_with_guest_token(): void
    {
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'display_name' => 'TestGuest',
        ]);

        $guestToken = $guestResponse->json('player.guest_token');

        $response = $this->getJson('/api/v1/auth/me', [
            'X-Guest-Token' => $guestToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'player' => [
                    'display_name' => 'TestGuest',
                    'is_guest' => true,
                ],
            ]);
    }
}
