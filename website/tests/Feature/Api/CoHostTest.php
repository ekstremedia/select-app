<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CoHostTest extends TestCase
{
    use RefreshDatabase;

    private Player $host;

    private string $hostToken;

    private Player $player2;

    private string $player2Token;

    private string $gameCode;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create host
        $response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Host']);
        $this->host = Player::find($response->json('player.id'));
        $this->hostToken = $response->json('player.guest_token');

        // Create second player
        $response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $this->player2 = Player::find($response->json('player.id'));
        $this->player2Token = $response->json('player.guest_token');

        // Create game and have player2 join
        $createResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games');
        $this->gameCode = $createResponse->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$this->gameCode}/join");
    }

    public function test_host_can_promote_player_to_co_host(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        $response->assertStatus(200)
            ->assertJson([
                'player_id' => $this->player2->id,
                'is_co_host' => true,
            ]);
    }

    public function test_host_can_demote_co_host(): void
    {
        // Promote first
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        // Demote
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        $response->assertStatus(200)
            ->assertJson([
                'player_id' => $this->player2->id,
                'is_co_host' => false,
            ]);
    }

    public function test_non_host_cannot_toggle_co_host(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->host->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only the host can manage co-hosts']);
    }

    public function test_host_cannot_change_own_co_host_status(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->host->id}");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cannot change your own co-host status']);
    }

    public function test_co_host_can_start_game(): void
    {
        // Promote player2 to co-host
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        // Co-host starts game
        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$this->gameCode}/start");

        $response->assertStatus(200)
            ->assertJson(['game' => ['status' => 'playing']]);
    }

    public function test_co_host_can_start_voting(): void
    {
        // Promote player2 to co-host
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        // Start game as host
        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/start");

        $roundId = $startResponse->json('round.id');

        // Both players submit answers
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$roundId}/answer", [
                'text' => $this->generateAnswer($startResponse->json('round.acronym')),
            ]);

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$roundId}/answer", [
                'text' => $this->generateAnswer($startResponse->json('round.acronym')),
            ]);

        // Co-host starts voting
        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$roundId}/voting");

        $response->assertStatus(200)
            ->assertJsonStructure(['round' => ['id', 'status']]);
    }

    public function test_regular_player_cannot_start_voting(): void
    {
        // Start game (no co-host promotion)
        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/start");

        $roundId = $startResponse->json('round.id');

        // Player2 tries to start voting without being co-host
        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$roundId}/voting");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only host or co-host can start voting']);
    }

    public function test_co_host_flag_included_in_game_response(): void
    {
        // Promote player2 to co-host
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        // Get game details
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$this->gameCode}");

        $response->assertStatus(200);

        $players = $response->json('game.players');
        $coHost = collect($players)->firstWhere('id', $this->player2->id);
        $this->assertTrue($coHost['is_co_host']);
    }

    public function test_host_can_toggle_visibility(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->patchJson("/api/v1/games/{$this->gameCode}/visibility", [
                'is_public' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson(['is_public' => true]);

        // Toggle back
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->patchJson("/api/v1/games/{$this->gameCode}/visibility", [
                'is_public' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson(['is_public' => false]);
    }

    public function test_co_host_can_toggle_visibility(): void
    {
        // Promote player2 to co-host
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/co-host/{$this->player2->id}");

        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->patchJson("/api/v1/games/{$this->gameCode}/visibility", [
                'is_public' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson(['is_public' => true]);
    }

    public function test_regular_player_cannot_toggle_visibility(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->patchJson("/api/v1/games/{$this->gameCode}/visibility", [
                'is_public' => true,
            ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only host or co-host can change visibility']);
    }

    /**
     * Generate a valid answer for a given acronym.
     */
    private function generateAnswer(string $acronym): string
    {
        $words = [];
        foreach (str_split($acronym) as $letter) {
            $words[] = $letter.'ord';
        }

        return implode(' ', $words);
    }
}
