<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(string $name = 'Test User', string $email = 'test@example.com'): array
    {
        $user = User::create([
            'name' => $name,
            'nickname' => 'TestNick',
            'email' => $email,
            'password' => bcrypt('password123'),
        ]);

        $player = Player::create([
            'user_id' => $user->id,
            'nickname' => 'TestNick',
            'is_guest' => false,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        return ['user' => $user, 'player' => $player, 'token' => $token];
    }

    public function test_can_update_name(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'New Name',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'New Name',
                    'email' => 'test@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', ['id' => $auth['user']->id, 'name' => 'New Name']);
    }

    public function test_can_update_email(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'email' => 'newemail@example.com',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => 'newemail@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', ['id' => $auth['user']->id, 'email' => 'newemail@example.com']);
    }

    public function test_can_update_name_and_email_together(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ]);
    }

    public function test_email_must_be_unique(): void
    {
        User::create([
            'name' => 'Other User',
            'nickname' => 'OtherNick',
            'email' => 'taken@example.com',
            'password' => bcrypt('password123'),
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'email' => 'taken@example.com',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_name_must_be_at_least_2_characters(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'A',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_email_must_be_valid(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'email' => 'not-an-email',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_keep_same_email(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/v1/profile', [
            'email' => 'test@example.com',
        ], [
            'Authorization' => "Bearer {$auth['token']}",
        ]);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_update_profile(): void
    {
        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Hacker',
        ]);

        $response->assertStatus(401);
    }
}
