<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPlayer(array $userOverrides = []): User
    {
        $user = User::factory()->create($userOverrides);
        Player::factory()->registered()->create([
            'user_id' => $user->id,
            'nickname' => $user->nickname,
        ]);

        return $user;
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $this->createUserWithPlayer(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['player', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUserWithPlayer(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_banned_user_cannot_login(): void
    {
        $this->createUserWithPlayer([
            'email' => 'banned@example.com',
            'is_banned' => true,
            'ban_reason' => 'Cheating',
            'banned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'banned@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Your account has been banned.',
                'reason' => 'Cheating',
            ]);
    }

    public function test_login_returns_player_stats(): void
    {
        $user = User::factory()->create(['email' => 'stats@example.com']);
        Player::factory()->registered()->create([
            'user_id' => $user->id,
            'nickname' => $user->nickname,
            'games_played' => 10,
            'games_won' => 3,
            'total_score' => 150,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'stats@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'player' => [
                    'stats' => [
                        'games_played' => 10,
                        'games_won' => 3,
                        'total_score' => 150,
                    ],
                ],
            ]);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->createUserWithPlayer(['email' => 'logout@example.com']);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }
}
