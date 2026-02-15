<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KickPlayerTest extends TestCase
{
    use RefreshDatabase;

    private Player $hostPlayer;

    private string $hostToken;

    private Player $guestPlayer;

    private string $guestToken;

    private Player $coHostPlayer;

    private string $coHostToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create host player (guest)
        $hostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'HostPlayer']);
        $this->hostPlayer = Player::find($hostResponse->json('player.id'));
        $this->hostToken = $hostResponse->json('player.guest_token');

        // Create guest player
        $guestResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'GuestPlayer']);
        $this->guestPlayer = Player::find($guestResponse->json('player.id'));
        $this->guestToken = $guestResponse->json('player.guest_token');

        // Create co-host player
        $coHostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'CoHostPlayer']);
        $this->coHostPlayer = Player::find($coHostResponse->json('player.id'));
        $this->coHostToken = $coHostResponse->json('player.guest_token');
    }

    private function createGameWithPlayers(): string
    {
        // Host creates game
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        // Guest joins
        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        // Co-host joins
        $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/join");

        // Make co-host
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/co-host/{$this->coHostPlayer->id}");

        return $code;
    }

    public function test_host_can_kick_player(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->guestPlayer->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'player_id' => $this->guestPlayer->id,
        ]);

        // Verify player is no longer active
        $stateResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$code}/state");

        $playerIds = collect($stateResponse->json('game.players'))->pluck('id');
        $this->assertNotContains($this->guestPlayer->id, $playerIds);
    }

    public function test_co_host_can_kick_player(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->guestPlayer->id}");

        $response->assertOk();
    }

    public function test_non_host_cannot_kick_player(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->coHostPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Only the host or co-host can kick players']);
    }

    public function test_cannot_kick_host(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->hostPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot kick the host']);
    }

    public function test_kicked_player_can_rejoin(): void
    {
        $code = $this->createGameWithPlayers();

        // Host kicks guest
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->guestPlayer->id}");

        // Guest can rejoin (kick is soft removal, not ban)
        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertOk();

        // Verify player is back in the active list
        $stateResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$code}/state");

        $playerIds = collect($stateResponse->json('game.players'))->pluck('id');
        $this->assertContains($this->guestPlayer->id, $playerIds->toArray());
    }

    public function test_cannot_kick_from_finished_game(): void
    {
        $code = $this->createGameWithPlayers();

        // Manually set game to finished
        $game = Game::where('code', $code)->first();
        $game->update(['status' => 'finished', 'finished_at' => now()]);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/kick/{$this->guestPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot kick players from a finished game']);
    }
}
