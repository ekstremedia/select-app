<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\BotSubmitAnswerJob;
use App\Application\Jobs\BotSubmitVoteJob;
use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BotFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Player $adminPlayer;

    private string $adminBearerToken;

    private Player $guestPlayer;

    private string $guestToken;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class, BotSubmitAnswerJob::class, BotSubmitVoteJob::class]);

        // Create admin user + player
        $adminUser = User::factory()->admin()->create();
        $this->adminPlayer = Player::factory()->create([
            'user_id' => $adminUser->id,
            'nickname' => 'AdminHost',
            'is_guest' => false,
            'guest_token' => null,
        ]);
        $this->adminBearerToken = $adminUser->createToken('test')->plainTextToken;

        // Create guest player
        $guestResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'GuestPlayer']);
        $guestResponse->assertStatus(201);
        $this->guestPlayer = Player::find($guestResponse->json('player.id'));
        $this->guestToken = $guestResponse->json('player.guest_token');
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminBearerToken}"];
    }

    public function test_admin_can_create_game_with_bots(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/games', [
                'is_public' => true,
                'add_bots' => true,
            ]);

        $response->assertStatus(201);

        $players = $response->json('game.players');
        $botPlayers = collect($players)->where('is_bot', true);

        // Should have 3-5 bots + the admin host
        $this->assertGreaterThanOrEqual(4, count($players));
        $this->assertLessThanOrEqual(6, count($players));
        $this->assertGreaterThanOrEqual(3, $botPlayers->count());
        $this->assertLessThanOrEqual(5, $botPlayers->count());
    }

    public function test_non_admin_cannot_add_bots(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games', [
            'is_public' => true,
            'add_bots' => true,
        ]);

        $response->assertStatus(201);

        $players = $response->json('game.players');
        // Only the guest player should be there (no bots)
        $this->assertCount(1, $players);
        $this->assertFalse($players[0]['is_bot']);
    }

    public function test_bot_players_show_is_bot_flag_in_game_response(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/games', [
                'is_public' => true,
                'add_bots' => true,
            ]);

        $response->assertStatus(201);

        $players = $response->json('game.players');

        // Host should not be a bot
        $host = collect($players)->firstWhere('is_host', true);
        $this->assertFalse($host['is_bot']);

        // At least one player should be a bot
        $bots = collect($players)->where('is_bot', true);
        $this->assertGreaterThan(0, $bots->count());

        foreach ($bots as $bot) {
            $this->assertTrue($bot['is_bot']);
        }
    }

    public function test_game_without_add_bots_has_no_bots(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/games', [
                'is_public' => true,
            ]);

        $response->assertStatus(201);

        $players = $response->json('game.players');
        $this->assertCount(1, $players);
        $this->assertFalse($players[0]['is_bot']);
    }

    public function test_bot_players_appear_in_game_show_endpoint(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/games', [
                'is_public' => true,
                'add_bots' => true,
            ]);

        $code = $createResponse->json('game.code');

        // Fetch game via show endpoint
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/games/{$code}");

        $response->assertStatus(200);

        $players = $response->json('game.players');
        $bots = collect($players)->where('is_bot', true);
        $this->assertGreaterThan(0, $bots->count());
    }
}
