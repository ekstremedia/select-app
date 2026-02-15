<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Broadcasting\Events\AnswerSubmittedBroadcast;
use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Application\Broadcasting\Events\RoundCompletedBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Application\Broadcasting\Events\VoteSubmittedBroadcast;
use App\Application\Http\Requests\Api\V1\SubmitAnswerRequest;
use App\Application\Http\Requests\Api\V1\SubmitVoteRequest;
use App\Domain\Game\Actions\GetGameByCodeAction;
use App\Domain\Round\Actions\CompleteRoundAction;
use App\Domain\Round\Actions\StartVotingAction;
use App\Domain\Round\Actions\SubmitAnswerAction;
use App\Domain\Round\Actions\SubmitVoteAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Round;
use App\Infrastructure\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoundController extends Controller
{
    public function current(string $code, GetGameByCodeAction $getGame): JsonResponse
    {
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        $round = $game->currentRoundModel();

        if (! $round) {
            return response()->json(['error' => 'No active round'], 404);
        }

        $response = [
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'acronym' => $round->acronym,
                'status' => $round->status,
                'answer_deadline' => $round->answer_deadline?->toIso8601String(),
                'vote_deadline' => $round->vote_deadline?->toIso8601String(),
            ],
        ];

        // Include answers if voting or completed
        if ($round->isVoting() || $round->isCompleted()) {
            $response['answers'] = $round->answers()->with('player')->get()->map(fn ($a) => [
                'id' => $a->id,
                'player_id' => $a->player_id,
                'player_name' => $a->author_nickname ?? $a->player->nickname,
                'text' => $a->text,
                'votes_count' => $round->isCompleted() ? $a->votes_count : null,
            ]);
        }

        return response()->json($response);
    }

    public function submitAnswer(SubmitAnswerRequest $request, string $roundId, SubmitAnswerAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');
        $round = Round::findOrFail($roundId);

        try {
            $answer = $action->execute($round, $player, $request->validated('text'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Broadcast answer submitted (just count, not content)
        $answersCount = $round->answers()->count();
        $totalPlayers = $round->game->activePlayers()->count();
        try {
            broadcast(new AnswerSubmittedBroadcast($round->game, $answersCount, $totalPlayers));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: answer.submitted', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'answer' => [
                'id' => $answer->id,
                'text' => $answer->text,
            ],
        ]);
    }

    public function submitVote(SubmitVoteRequest $request, string $roundId, SubmitVoteAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');
        $round = Round::findOrFail($roundId);

        $answer = Answer::findOrFail($request->validated('answer_id'));

        try {
            $vote = $action->execute($round, $player, $answer);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Broadcast vote submitted (just count)
        $game = $round->game;
        $totalVoters = $game->activePlayers()->count();
        $uniqueVoters = Vote::whereHas('answer', fn ($q) => $q->where('round_id', $round->id))->distinct('voter_id')->count('voter_id');
        try {
            broadcast(new VoteSubmittedBroadcast($game, $uniqueVoters, $totalVoters));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: vote.submitted', ['error' => $e->getMessage()]);
        }

        // Timer runs out naturally — Delectus completes the round when vote_deadline passes.
        // This allows players to change their vote until time runs out.

        return response()->json([
            'vote' => [
                'id' => $vote->id,
                'answer_id' => $vote->answer_id,
            ],
        ]);
    }

    public function startVoting(Request $request, string $roundId, StartVotingAction $action): JsonResponse
    {
        $round = Round::findOrFail($roundId);
        $player = $request->attributes->get('player');

        if (! $round->game->isHostOrCoHost($player)) {
            return response()->json(['error' => 'Only host or co-host can start voting'], 403);
        }

        try {
            $round = $action->execute($round);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // StartVotingAction already broadcasts voting.started with anonymous answers
        // Return anonymous answer list to the host too
        $answers = $round->answers()->get()->map(fn ($a) => [
            'id' => $a->id,
            'text' => $a->text,
        ]);

        return response()->json([
            'round' => [
                'id' => $round->id,
                'status' => $round->status,
                'vote_deadline' => $round->vote_deadline?->toIso8601String(),
            ],
            'answers' => $answers,
        ]);
    }

    public function complete(Request $request, string $roundId, CompleteRoundAction $action): JsonResponse
    {
        $round = Round::findOrFail($roundId);
        $player = $request->attributes->get('player');

        if (! $round->game->isHostOrCoHost($player)) {
            return response()->json(['error' => 'Only host or co-host can complete round'], 403);
        }

        try {
            $result = $action->execute($round);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            broadcast(new RoundCompletedBroadcast($round->game, $result['round_results']));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: round.completed', ['error' => $e->getMessage()]);
        }

        if ($result['game_finished']) {
            try {
                broadcast(new GameFinishedBroadcast($round->game, $result['final_scores']));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: game.finished', ['error' => $e->getMessage()]);
            }
        } elseif (isset($result['next_round'])) {
            try {
                broadcast(new RoundStartedBroadcast($round->game, $result['next_round']));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: round.started', ['error' => $e->getMessage()]);
            }
        }

        return response()->json($result);
    }
}
