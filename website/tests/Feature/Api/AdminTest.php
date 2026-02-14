<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->adminToken = $this->admin->createToken('api')->plainTextToken;
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    public function test_admin_can_list_players(): void
    {
        Player::factory()->count(3)->create();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/players');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_search_players(): void
    {
        Player::factory()->create(['nickname' => 'SearchMe']);
        Player::factory()->create(['nickname' => 'Other']);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/players?search=Search');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_admin_can_list_games(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/games');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_ban_player(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->registered()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/ban', [
                'player_id' => $player->id,
                'reason' => 'Cheating in games',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Player has been banned.']);

        $user->refresh();
        $this->assertTrue($user->is_banned);
        $this->assertEquals('Cheating in games', $user->ban_reason);
    }

    public function test_admin_can_unban_player(): void
    {
        $user = User::factory()->banned()->create();
        $player = Player::factory()->registered()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/admin/unban/{$player->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Player has been unbanned.']);

        $user->refresh();
        $this->assertFalse($user->is_banned);
        $this->assertNull($user->ban_reason);
    }

    public function test_ban_requires_reason(): void
    {
        $player = Player::factory()->create();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/ban', [
                'player_id' => $player->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_ban_requires_valid_player_id(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/ban', [
                'player_id' => '00000000-0000-0000-0000-000000000000',
                'reason' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(); // Regular user
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/admin/players');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/v1/admin/players');

        $response->assertStatus(401);
    }
}
