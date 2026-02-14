<?php

namespace Tests\Feature\Middleware;

use App\Infrastructure\Models\BannedIp;
use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_banned_user_cannot_access_protected_routes(): void
    {
        $user = User::factory()->banned('Cheating')->create();
        $player = Player::factory()->registered()->create(['user_id' => $user->id]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Your account has been banned.',
                'reason' => 'Cheating',
            ]);
    }

    public function test_non_banned_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->registered()->create([
            'user_id' => $user->id,
            'nickname' => $user->nickname,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_ip_banned_guest_cannot_access_protected_routes(): void
    {
        $guest = Player::factory()->guest()->create();

        BannedIp::create([
            'ip_address' => '127.0.0.1',
            'reason' => 'Spam',
        ]);

        $response = $this->withHeaders([
            'X-Guest-Token' => $guest->guest_token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Your IP address has been banned.']);
    }

    public function test_guest_without_ip_ban_can_access_routes(): void
    {
        $guest = Player::factory()->guest()->create();

        $response = $this->withHeaders([
            'X-Guest-Token' => $guest->guest_token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
    }

    public function test_request_without_player_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
