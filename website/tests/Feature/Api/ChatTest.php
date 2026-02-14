<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private string $guestToken;

    private string $gameCode;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create a guest player
        $response = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ChatPlayer',
        ]);

        $this->player = Player::find($response->json('player.id'));
        $this->guestToken = $response->json('player.guest_token');

        // Create a game for chat tests
        $createResponse = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games');

        $this->gameCode = $createResponse->json('game.code');
    }

    public function test_can_send_chat_message(): void
    {
        Event::fake();

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/chat", [
            'message' => 'Hello everyone!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['sent' => true]);
    }

    public function test_chat_requires_message_field(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/chat", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_message_must_be_string(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/chat", [
            'message' => 12345,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_message_max_200_characters(): void
    {
        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/chat", [
            'message' => str_repeat('a', 201),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_message_at_exactly_200_characters_succeeds(): void
    {
        Event::fake();

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson("/api/v1/games/{$this->gameCode}/chat", [
            'message' => str_repeat('a', 200),
        ]);

        $response->assertStatus(200)
            ->assertJson(['sent' => true]);
    }

    public function test_chat_requires_authentication(): void
    {
        $response = $this->flushHeaders()->postJson("/api/v1/games/{$this->gameCode}/chat", [
            'message' => 'Hello!',
        ]);

        $response->assertStatus(401);
    }

    public function test_chat_returns_404_for_invalid_game(): void
    {
        Event::fake();

        $response = $this->withHeaders([
            'X-Guest-Token' => $this->guestToken,
        ])->postJson('/api/v1/games/XXXXXX/chat', [
            'message' => 'Hello!',
        ]);

        $response->assertStatus(404);
    }
}
