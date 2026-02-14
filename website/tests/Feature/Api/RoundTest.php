<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RoundTest extends TestCase
{
    use RefreshDatabase;

    private Player $host;

    private Player $player2;

    private string $hostToken;

    private string $player2Token;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent ProcessAnswerDeadlineJob from running synchronously in tests
        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create host
        $hostResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'HostPlayer']);
        $this->host = Player::find($hostResponse->json('player.id'));
        $this->hostToken = $hostResponse->json('player.guest_token');

        // Create player 2
        $p2Response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $this->player2 = Player::find($p2Response->json('player.id'));
        $this->player2Token = $p2Response->json('player.guest_token');

        // Create and start a game
        $createResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['settings' => ['answer_time' => 120, 'vote_time' => 60]]);

        $code = $createResponse->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/start");

        $this->game = Game::find($startResponse->json('game.id'));
    }

    public function test_can_get_current_round(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$this->game->code}/rounds/current");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'round' => [
                    'id',
                    'round_number',
                    'acronym',
                    'status',
                    'answer_deadline',
                ],
            ]);

        $this->assertEquals('answering', $response->json('round.status'));
        $this->assertEquals(1, $response->json('round.round_number'));
    }

    public function test_can_submit_valid_answer(): void
    {
        $round = $this->game->currentRoundModel();
        $acronym = $round->acronym;

        // Generate a valid answer
        $words = array_map(fn ($letter) => $letter.'ord', str_split($acronym));
        $answer = implode(' ', $words);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", [
                'text' => $answer,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'answer' => ['id', 'text'],
            ]);
    }

    public function test_cannot_submit_invalid_answer(): void
    {
        $round = $this->game->currentRoundModel();

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", [
                'text' => 'This does not match',
            ]);

        $response->assertStatus(422);
    }

    public function test_player_not_in_game_cannot_submit(): void
    {
        // Create a player not in the game
        $outsiderResponse = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Outsider']);
        $outsiderToken = $outsiderResponse->json('player.guest_token');

        $round = $this->game->currentRoundModel();
        $acronym = $round->acronym;
        $words = array_map(fn ($letter) => $letter.'ord', str_split($acronym));
        $answer = implode(' ', $words);

        $response = $this->withHeaders(['X-Guest-Token' => $outsiderToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", [
                'text' => $answer,
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Player is not in this game']);
    }

    public function test_host_can_start_voting(): void
    {
        $round = $this->game->currentRoundModel();

        // Both players submit answers
        $acronym = $round->acronym;
        $words = array_map(fn ($letter) => $letter.'ord', str_split($acronym));
        $answer = implode(' ', $words);

        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => $answer]);

        $words2 = array_map(fn ($letter) => $letter.'est', str_split($acronym));
        $answer2 = implode(' ', $words2);

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => $answer2]);

        // Host starts voting
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/voting");

        $response->assertStatus(200)
            ->assertJson(['round' => ['status' => 'voting']])
            ->assertJsonStructure(['answers']);
    }

    public function test_can_vote_for_others_answer(): void
    {
        $round = $this->game->currentRoundModel();
        $acronym = $round->acronym;

        // Both submit answers
        $words = array_map(fn ($letter) => $letter.'ord', str_split($acronym));
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => implode(' ', $words)]);

        $words2 = array_map(fn ($letter) => $letter.'est', str_split($acronym));
        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => implode(' ', $words2)]);

        // Start voting
        $votingResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/voting");

        $answers = $votingResponse->json('answers');
        $player2Answer = collect($answers)->firstWhere('player_id', $this->player2->id);

        // Host votes for player2's answer
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/vote", [
                'answer_id' => $player2Answer['id'],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['vote' => ['id', 'answer_id']]);
    }

    public function test_cannot_vote_for_own_answer(): void
    {
        $round = $this->game->currentRoundModel();
        $acronym = $round->acronym;

        // Submit answers
        $words = array_map(fn ($letter) => $letter.'ord', str_split($acronym));
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => implode(' ', $words)]);

        $words2 = array_map(fn ($letter) => $letter.'est', str_split($acronym));
        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$round->id}/answer", ['text' => implode(' ', $words2)]);

        // Start voting
        $votingResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/voting");

        $answers = $votingResponse->json('answers');
        $hostAnswer = collect($answers)->firstWhere('player_id', $this->host->id);

        // Host tries to vote for own answer
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/vote", [
                'answer_id' => $hostAnswer['id'],
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cannot vote for your own answer']);
    }
}
