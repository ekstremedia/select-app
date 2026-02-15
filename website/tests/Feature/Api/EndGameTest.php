<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndGameTest extends TestCase
{
    use RefreshDatabase;

    private Player $hostPlayer;

    private string $hostToken;

    private Player $guestPlayer;

    private string $guestToken;

    protected function setUp(): void
    {
        parent::setUp();

        $hostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'HostPlayer']);
        $this->hostPlayer = Player::find($hostResponse->json('player.id'));
        $this->hostToken = $hostResponse->json('player.guest_token');

        $guestResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'GuestPlayer']);
        $this->guestPlayer = Player::find($guestResponse->json('player.id'));
        $this->guestToken = $guestResponse->json('player.guest_token');
    }

    public function test_host_can_end_lobby_game(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/end");

        $response->assertOk();

        $game = Game::where('code', $code)->first();
        $this->assertEquals('finished', $game->status);
        $this->assertEquals('cancelled', $game->settings['finished_reason']);
    }

    public function test_non_host_cannot_end_game(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/join");

        $response = $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$code}/end");

        $response->assertStatus(403);
    }

    public function test_keepalive_touches_game(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $game = Game::where('code', $code)->first();
        $oldUpdatedAt = $game->updated_at;

        // Small delay so timestamps differ
        $this->travel(1)->seconds();

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/keepalive");

        $response->assertOk();

        $game->refresh();
        $this->assertTrue($game->updated_at->gt($oldUpdatedAt));
    }

    public function test_lobby_timeout_warning_and_close(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $game = Game::where('code', $code)->first();

        // Simulate 6 minutes of inactivity
        $game->timestamps = false;
        $game->updated_at = now()->subMinutes(6);
        $game->save();
        $game->timestamps = true;

        // First tick: should send warning
        $processor = app(\App\Domain\Delectus\GameProcessor::class);
        $processor->processLobby($game->fresh());

        $game->refresh();
        $this->assertNotNull($game->settings['lobby_warning_at'] ?? null);

        // Simulate 61 seconds after warning
        $settings = $game->settings;
        $settings['lobby_warning_at'] = now()->subSeconds(61)->toIso8601String();
        \Illuminate\Support\Facades\DB::table('games')->where('id', $game->id)->update([
            'settings' => json_encode($settings),
        ]);

        $processor->processLobby($game->fresh());

        $game->refresh();
        $this->assertEquals('finished', $game->status);
        $this->assertEquals('lobby_timeout', $game->settings['finished_reason']);
    }

    public function test_keepalive_clears_lobby_warning(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['is_public' => true]);
        $code = $response->json('game.code');

        $game = Game::where('code', $code)->first();

        // Set a warning
        $settings = $game->settings;
        $settings['lobby_warning_at'] = now()->subSeconds(10)->toIso8601String();
        $game->update(['settings' => $settings]);

        // Keepalive (which touches updated_at, making it newer than lobby_warning_at)
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/keepalive");

        // Process lobby — warning should be cleared
        $processor = app(\App\Domain\Delectus\GameProcessor::class);
        $processor->processLobby($game->fresh());

        $game->refresh();
        $this->assertNull($game->settings['lobby_warning_at'] ?? null);
    }
}
