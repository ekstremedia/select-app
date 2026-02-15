<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnonymousVotingTest extends TestCase
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

    public function test_state_hides_player_info_during_voting(): void
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
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/voting");

        // Get state during voting
        $stateResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$this->game->code}/state");

        $stateResponse->assertStatus(200);

        // During voting, answers should NOT contain player_id or player_name
        $answers = $stateResponse->json('answers');
        $this->assertNotEmpty($answers);
        foreach ($answers as $answer) {
            $this->assertArrayNotHasKey('player_id', $answer);
            $this->assertArrayNotHasKey('player_name', $answer);
        }
    }

    public function test_state_shows_player_info_after_round_completed(): void
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
        $player2AnswerText = mb_strtolower(implode(' ', $words2));
        $player2Answer = collect($answers)->firstWhere('text', $player2AnswerText);

        // Both vote
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/vote", ['answer_id' => $player2Answer['id']]);

        $hostAnswerText = mb_strtolower(implode(' ', $words));
        $hostAnswer = collect($answers)->firstWhere('text', $hostAnswerText);
        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$round->id}/vote", ['answer_id' => $hostAnswer['id']]);

        // Complete the round
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$round->id}/complete");

        // Get state after round completed
        $stateResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$this->game->code}/state");

        $stateResponse->assertStatus(200);

        // After completion, answers should contain player_id and player_name
        $answersInState = $stateResponse->json('answers');
        $this->assertNotEmpty($answersInState);
        foreach ($answersInState as $answer) {
            $this->assertArrayHasKey('player_id', $answer);
            $this->assertArrayHasKey('player_name', $answer);
        }
    }
}
