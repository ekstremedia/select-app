<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_nickname(): void
    {
        // Create a guest player
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'OldNickname',
        ]);

        $guestToken = $guestResponse->json('player.guest_token');

        // Update nickname
        $response = $this->patchJson('/api/v1/profile/nickname', [
            'nickname' => 'NewNickname',
        ], [
            'X-Guest-Token' => $guestToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'player' => [
                    'nickname' => 'NewNickname',
                ],
            ]);

        // Verify in database
        $player = Player::where('guest_token', $guestToken)->first();
        $this->assertEquals('NewNickname', $player->nickname);
    }

    public function test_update_nickname_validates_format(): void
    {
        // Create a guest player
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ValidName',
        ]);

        $guestToken = $guestResponse->json('player.guest_token');

        // Try to update with invalid nickname (too short + special chars)
        $response = $this->patchJson('/api/v1/profile/nickname', [
            'nickname' => 'ab!',
        ], [
            'X-Guest-Token' => $guestToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_update_nickname_must_be_unique(): void
    {
        // Create two guest players
        $guest1Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player1',
        ]);

        $guest2Response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'Player2',
        ]);

        $guest2Token = $guest2Response->json('player.guest_token');

        // Try to set Player2's nickname to Player1's nickname
        $response = $this->patchJson('/api/v1/profile/nickname', [
            'nickname' => 'Player1',
        ], [
            'X-Guest-Token' => $guest2Token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_update_nickname_ignores_own_current_nickname(): void
    {
        // Create a guest player
        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'MyNickname',
        ]);

        $guestToken = $guestResponse->json('player.guest_token');

        // Try to set nickname to same value
        $response = $this->patchJson('/api/v1/profile/nickname', [
            'nickname' => 'MyNickname',
        ], [
            'X-Guest-Token' => $guestToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'player' => [
                    'nickname' => 'MyNickname',
                ],
            ]);
    }

    public function test_delete_account_removes_user_and_player(): void
    {
        // Register a user
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'email' => 'delete@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Delete Test',
            'nickname' => 'DeleteMe',
        ]);

        $token = $registerResponse->json('token');
        $userId = $registerResponse->json('user.id');
        $playerId = $registerResponse->json('player.id');

        // Delete account
        $response = $this->deleteJson('/api/v1/profile', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Account deleted successfully.']);

        // Verify user and player are deleted
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('players', ['id' => $playerId]);
    }

    public function test_delete_account_requires_authentication(): void
    {
        // Try to delete without authentication
        $response = $this->deleteJson('/api/v1/profile');

        $response->assertStatus(401);
    }
}
