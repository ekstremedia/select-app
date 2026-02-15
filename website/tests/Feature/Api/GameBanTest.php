<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBanTest extends TestCase
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

        $hostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'HostPlayer']);
        $this->hostPlayer = Player::find($hostResponse->json('player.id'));
        $this->hostToken = $hostResponse->json('player.guest_token');

        $guestResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'GuestPlayer']);
        $this->guestPlayer = Player::find($guestResponse->json('player.id'));
        $this->guestToken = $guestResponse->json('player.guest_token');

        $coHostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'CoHostPlayer']);
        $this->coHostPlayer = Player::find($coHostResponse->json('player.id'));
        $this->coHostToken = $coHostResponse->json('player.guest_token');
    }

    private function createGameWithPlayers(): string
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/join");

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/co-host/{$this->coHostPlayer->id}");

        return $code;
    }

    public function test_host_can_ban_player_without_reason(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

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

        // Verify banned player appears in banned_players list
        $bannedPlayers = collect($stateResponse->json('game.banned_players'));
        $this->assertTrue($bannedPlayers->contains('id', $this->guestPlayer->id));
    }

    public function test_host_can_ban_player_with_reason(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}", [
                'reason' => 'Spamming',
            ]);

        $response->assertOk();

        $stateResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$code}/state");

        $bannedPlayers = collect($stateResponse->json('game.banned_players'));
        $banned = $bannedPlayers->firstWhere('id', $this->guestPlayer->id);
        $this->assertEquals('Spamming', $banned['ban_reason']);
    }

    public function test_co_host_can_ban_player(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        $response->assertOk();
    }

    public function test_non_host_cannot_ban_player(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->coHostPlayer->id}");

        $response->assertForbidden();
        $response->assertJson(['error' => 'Only the host or co-host can ban players']);
    }

    public function test_cannot_ban_host(): void
    {
        $code = $this->createGameWithPlayers();

        $response = $this->withHeaders(['X-Guest-Token' => $this->coHostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->hostPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot ban the host']);
    }

    public function test_banned_player_cannot_rejoin(): void
    {
        $code = $this->createGameWithPlayers();

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Du er utestengt fra dette spillet']);
    }

    public function test_host_can_unban_player(): void
    {
        $code = $this->createGameWithPlayers();

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/unban/{$this->guestPlayer->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'player_id' => $this->guestPlayer->id,
        ]);
    }

    public function test_unbanned_player_can_rejoin(): void
    {
        $code = $this->createGameWithPlayers();

        // Ban then unban
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/unban/{$this->guestPlayer->id}");

        // Player can now rejoin
        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        $response->assertOk();
    }

    public function test_cannot_ban_from_finished_game(): void
    {
        $code = $this->createGameWithPlayers();

        $game = Game::where('code', $code)->first();
        $game->update(['status' => 'finished', 'finished_at' => now()]);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot ban players from a finished game']);
    }

    public function test_unban_only_works_in_lobby(): void
    {
        $code = $this->createGameWithPlayers();

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/ban/{$this->guestPlayer->id}");

        // Set game to playing
        $game = Game::where('code', $code)->first();
        $game->update(['status' => 'playing']);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/unban/{$this->guestPlayer->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Can only unban players in the lobby']);
    }
}
