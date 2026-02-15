<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RoundReadyTest extends TestCase
{
    use RefreshDatabase;

    private Player $host;

    private string $hostToken;

    private Player $player2;

    private string $player2Token;

    private string $gameCode;

    private string $roundId;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create host
        $response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Host']);
        $this->host = Player::find($response->json('player.id'));
        $this->hostToken = $response->json('player.guest_token');

        // Create player 2
        $response = $this->postJson('/api/v1/auth/guest', ['nickname' => 'Player2']);
        $this->player2 = Player::find($response->json('player.id'));
        $this->player2Token = $response->json('player.guest_token');

        // Create game, join, and start
        $createResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['settings' => ['answer_time' => 120]]);
        $this->gameCode = $createResponse->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$this->gameCode}/join");

        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$this->gameCode}/start");

        $this->roundId = $startResponse->json('round.id');
    }

    public function test_can_mark_ready_after_submitting_answer(): void
    {
        // Submit answer
        $acronym = Round::find($this->roundId)->acronym;
        $answer = $this->makeAnswerForAcronym($acronym);
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);

        // Mark ready
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        $response->assertStatus(200)
            ->assertJson([
                'ready' => true,
                'ready_count' => 1,
                'total_players' => 2,
            ]);
    }

    public function test_can_toggle_ready_off(): void
    {
        $acronym = Round::find($this->roundId)->acronym;
        $answer = $this->makeAnswerForAcronym($acronym);
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);

        // Mark ready then unmark
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => false]);

        $response->assertStatus(200)
            ->assertJson([
                'ready' => false,
                'ready_count' => 0,
            ]);
    }

    public function test_cannot_mark_ready_without_answer(): void
    {
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Player has not submitted an answer']);
    }

    public function test_cannot_mark_ready_in_wrong_phase(): void
    {
        // Force round to voting
        $round = Round::find($this->roundId);
        $round->update(['status' => 'voting']);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Round is not in the answering phase']);
    }

    public function test_all_ready_advances_deadline(): void
    {
        $round = Round::find($this->roundId);
        $acronym = $round->acronym;
        $answer = $this->makeAnswerForAcronym($acronym);

        // Both players submit
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);
        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);

        // Record original deadline
        $originalDeadline = $round->fresh()->answer_deadline;

        // Both mark ready
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);
        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        // Deadline should be set to now (or very close)
        $updatedRound = $round->fresh();
        $this->assertTrue($updatedRound->answer_deadline->lte(now()->addSeconds(2)));
        $this->assertTrue($updatedRound->answer_deadline->lt($originalDeadline));
    }

    public function test_edit_resets_is_ready(): void
    {
        $round = Round::find($this->roundId);
        $acronym = $round->acronym;
        $answer = $this->makeAnswerForAcronym($acronym);
        $altAnswer = $this->makeAltAnswerForAcronym($acronym);

        // Submit and mark ready
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        // Edit answer
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $altAnswer]);

        // is_ready should be false
        $answerModel = $round->answers()->where('player_id', $this->host->id)->first();
        $this->assertFalse($answerModel->is_ready);
    }

    public function test_cannot_mark_ready_when_disabled(): void
    {
        // Create a game with ready check disabled
        $createResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson('/api/v1/games', ['settings' => ['allow_ready_check' => false, 'answer_time' => 120]]);
        $code = $createResponse->json('game.code');

        $this->withHeaders(['X-Guest-Token' => $this->player2Token])
            ->postJson("/api/v1/games/{$code}/join");

        $startResponse = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/games/{$code}/start");
        $roundId = $startResponse->json('round.id');

        $round = Round::find($roundId);
        $answer = $this->makeAnswerForAcronym($round->acronym);
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$roundId}/answer", ['text' => $answer]);

        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$roundId}/ready", ['ready' => true]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Ready check is not enabled for this game']);
    }

    public function test_state_includes_ready_data(): void
    {
        $round = Round::find($this->roundId);
        $acronym = $round->acronym;
        $answer = $this->makeAnswerForAcronym($acronym);

        // Submit and mark ready
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/answer", ['text' => $answer]);
        $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->postJson("/api/v1/rounds/{$this->roundId}/ready", ['ready' => true]);

        // Fetch state
        $response = $this->withHeaders(['X-Guest-Token' => $this->hostToken])
            ->getJson("/api/v1/games/{$this->gameCode}/state");

        $response->assertStatus(200)
            ->assertJsonPath('my_answer.is_ready', true)
            ->assertJsonPath('round.ready_count', 1)
            ->assertJsonPath('round.total_players', 2);
    }

    /**
     * Generate a valid answer for the given acronym using simple words.
     */
    private function makeAnswerForAcronym(string $acronym): string
    {
        $words = [];
        foreach (str_split($acronym) as $letter) {
            $words[] = $this->wordForLetter(strtolower($letter));
        }

        return implode(' ', $words);
    }

    /**
     * Generate an alternative valid answer for the given acronym.
     */
    private function makeAltAnswerForAcronym(string $acronym): string
    {
        $words = [];
        foreach (str_split($acronym) as $letter) {
            $words[] = $this->altWordForLetter(strtolower($letter));
        }

        return implode(' ', $words);
    }

    private function wordForLetter(string $letter): string
    {
        $map = [
            'a' => 'alle', 'b' => 'bare', 'c' => 'cool', 'd' => 'den',
            'e' => 'ekte', 'f' => 'fin', 'g' => 'god', 'h' => 'har',
            'i' => 'inn', 'j' => 'jeg', 'k' => 'kan', 'l' => 'liker',
            'm' => 'meg', 'n' => 'noe', 'o' => 'opp', 'p' => 'pen',
            'q' => 'quiz', 'r' => 'rar', 's' => 'ser', 't' => 'tok',
            'u' => 'ute', 'v' => 'vil', 'w' => 'wen', 'x' => 'xtra',
            'y' => 'yre', 'z' => 'zoo',
        ];

        return $map[$letter] ?? $letter;
    }

    private function altWordForLetter(string $letter): string
    {
        $map = [
            'a' => 'apen', 'b' => 'bra', 'c' => 'chill', 'd' => 'din',
            'e' => 'en', 'f' => 'fisk', 'g' => 'glad', 'h' => 'hei',
            'i' => 'ikke', 'j' => 'ja', 'k' => 'kul', 'l' => 'lang',
            'm' => 'mat', 'n' => 'nei', 'o' => 'og', 'p' => 'prat',
            'q' => 'quilt', 'r' => 'rask', 's' => 'snill', 't' => 'topp',
            'u' => 'under', 'v' => 'var', 'w' => 'wow', 'x' => 'xen',
            'y' => 'yay', 'z' => 'zen',
        ];

        return $map[$letter] ?? $letter;
    }
}
