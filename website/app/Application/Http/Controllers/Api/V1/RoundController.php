<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Broadcasting\Events\AnswerSubmittedBroadcast;
use App\Application\Broadcasting\Events\GameFinishedBroadcast;
use App\Application\Broadcasting\Events\RoundCompletedBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Application\Broadcasting\Events\VoteSubmittedBroadcast;
use App\Application\Broadcasting\Events\VotingStartedBroadcast;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        broadcast(new AnswerSubmittedBroadcast($round->game, $answersCount, $totalPlayers));

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
        $votesCount = $round->answers()->sum('votes_count');
        $totalVoters = $round->game->activePlayers()->count();
        broadcast(new VoteSubmittedBroadcast($round->game, $votesCount, $totalVoters));

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

        if ($round->game->host_player_id !== $player->id) {
            return response()->json(['error' => 'Only host can start voting'], 403);
        }

        try {
            $round = $action->execute($round);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $answers = $round->answers()->with('player')->get()->map(fn ($a) => [
            'id' => $a->id,
            'player_id' => $a->player_id,
            'player_name' => $a->author_nickname ?? $a->player->nickname,
            'text' => $a->text,
        ]);

        broadcast(new VotingStartedBroadcast($round->game, $round, $answers->toArray()));

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

        if ($round->game->host_player_id !== $player->id) {
            return response()->json(['error' => 'Only host can complete round'], 403);
        }

        try {
            $result = $action->execute($round);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        broadcast(new RoundCompletedBroadcast($round->game, $result['round_results']));

        if ($result['game_finished']) {
            broadcast(new GameFinishedBroadcast($round->game, $result['final_scores']));
        } elseif (isset($result['next_round'])) {
            broadcast(new RoundStartedBroadcast($round->game, $result['next_round']));
        }

        return response()->json($result);
    }
}
