<?php

namespace Tests\Feature\Api;

use App\Application\Mail\GameInviteMail;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GameInviteTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private string $guestToken;

    private string $gameCode;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Create a guest player
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'InviteHost',
        ]);

        $this->player = Player::find($response->json('player.id'));
        $this->guestToken = $response->json('player.guest_token');

        // Create a game
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $this->gameCode = $createResponse->json('game.code');
    }

    public function test_can_send_invite_email(): void
    {
        RateLimiter::clear('game-invite:'.$this->player->id);

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['sent' => true])
            ->assertJsonStructure(['invites_remaining']);

        Mail::assertQueued(GameInviteMail::class, function (GameInviteMail $mail) {
            return $mail->hasTo('friend@example.com')
                && $mail->inviter->id === $this->player->id
                && $mail->game->code === $this->gameCode;
        });
    }

    public function test_invite_requires_email(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_requires_valid_email(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_requires_authentication(): void
    {
        $response = $this->flushHeaders()->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_invite_returns_404_for_invalid_game(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games/XXXXXX/invite', [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_invite_only_works_in_lobby(): void
    {
        // Add a second player so we can start the game
        $response2 = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $guestToken2 = $response2->json('player.guest_token');

        $this->withHeaders(['X-Guest-Token' => $guestToken2])
            ->postJson("/api/v1/games/{$this->gameCode}/join");

        // Start the game
        $this->withHeaders(['X-Guest-Token' => $this->guestToken])
            ->postJson("/api/v1/games/{$this->gameCode}/start");

        // Try to invite
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invites can only be sent from the lobby']);
    }

    public function test_invite_only_allowed_for_participants(): void
    {
        // Create a different player who is NOT in the game
        $response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Outsider']);
        $outsiderToken = $response->json('player.guest_token');

        $response = $this->withHeaders([
            'X-Guest-Token' => $outsiderToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only game participants can send invites']);
    }

    public function test_invite_rate_limited_to_5_per_10_minutes(): void
    {
        RateLimiter::clear('game-invite:'.$this->player->id);

        $headers = ['X-Guest-Token' => $this->guestToken];

        // Send 5 invites (should all succeed)
        for ($i = 1; $i <= 5; $i++) {
            $this->withHeaders($headers)
                ->postJson("/api/v1/games/{$this->gameCode}/invite", [
                    'email' => "friend{$i}@example.com",
                ])
                ->assertStatus(200);
        }

        // 6th should be rate limited
        $this->withHeaders($headers)
            ->postJson("/api/v1/games/{$this->gameCode}/invite", [
                'email' => 'friend6@example.com',
            ])
            ->assertStatus(429);

        Mail::assertQueuedCount(5);
    }

    public function test_invite_returns_remaining_count(): void
    {
        RateLimiter::clear('game-invite:'.$this->player->id);

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/invite", [
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->json('invites_remaining'));
        $this->assertGreaterThanOrEqual(0, $response->json('invites_remaining'));
    }
}
