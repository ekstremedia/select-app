<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Broadcasting\Events\ChatMessageBroadcast;
use App\Application\Broadcasting\Events\GameStartedBroadcast;
use App\Application\Broadcasting\Events\PlayerJoinedBroadcast;
use App\Application\Broadcasting\Events\PlayerLeftBroadcast;
use App\Application\Broadcasting\Events\RoundStartedBroadcast;
use App\Application\Http\Requests\Api\V1\CreateGameRequest;
use App\Application\Http\Requests\Api\V1\JoinGameRequest;
use App\Domain\Game\Actions\CreateGameAction;
use App\Domain\Game\Actions\GetGameByCodeAction;
use App\Domain\Game\Actions\JoinGameAction;
use App\Domain\Game\Actions\LeaveGameAction;
use App\Domain\Game\Actions\StartGameAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::publicLobby()
            ->withCount(['gamePlayers as player_count' => function ($q) {
                $q->where('is_active', true);
            }])
            ->with('host')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($game) => [
                'code' => $game->code,
                'host_nickname' => $game->host?->nickname,
                'player_count' => $game->player_count,
                'max_players' => $game->settings['max_players'] ?? 10,
                'rounds' => $game->settings['rounds'] ?? 8,
                'has_password' => ! is_null($game->password),
            ]);

        return response()->json(['games' => $games]);
    }

    public function store(CreateGameRequest $request, CreateGameAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');

        $game = $action->execute(
            $player,
            $request->validated('settings', []),
            (bool) $request->validated('is_public', false),
            $request->validated('password'),
        );

        return response()->json([
            'game' => $this->formatGame($game),
        ], 201);
    }

    public function show(string $code, GetGameByCodeAction $action): JsonResponse
    {
        $game = $action->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        return response()->json([
            'game' => $this->formatGame($game),
        ]);
    }

    public function join(JoinGameRequest $request, string $code, GetGameByCodeAction $getGame, JoinGameAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $action->execute($game, $player, $request->validated('password'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            broadcast(new PlayerJoinedBroadcast($game, $player))->toOthers();
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: player.joined', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'game' => $this->formatGame($game->fresh()),
        ]);
    }

    public function leave(Request $request, string $code, GetGameByCodeAction $getGame, LeaveGameAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $action->execute($game, $player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            broadcast(new PlayerLeftBroadcast($game, $player))->toOthers();
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: player.left', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function start(Request $request, string $code, GetGameByCodeAction $getGame, StartGameAction $action): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        try {
            $game = $action->execute($game, $player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $round = $game->currentRoundModel();

        try {
            broadcast(new GameStartedBroadcast($game));
            broadcast(new RoundStartedBroadcast($game, $round));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: game.started', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'game' => $this->formatGame($game),
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'acronym' => $round->acronym,
                'status' => $round->status,
                'answer_deadline' => $round->answer_deadline?->toIso8601String(),
            ],
        ]);
    }

    public function state(Request $request, string $code, GetGameByCodeAction $getGame): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        $response = ['game' => $this->formatGame($game)];

        // Include current round info if game is active
        $round = $game->currentRoundModel();
        if ($round) {
            $response['round'] = [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'acronym' => $round->acronym,
                'status' => $round->status,
                'answer_deadline' => $round->answer_deadline?->toIso8601String(),
                'vote_deadline' => $round->vote_deadline?->toIso8601String(),
            ];

            // Check if current player has submitted an answer
            $myAnswer = $round->answers()->where('player_id', $player->id)->first();
            if ($myAnswer) {
                $response['my_answer'] = [
                    'id' => $myAnswer->id,
                    'text' => $myAnswer->text,
                ];
            }

            // Include answers if voting or completed
            if ($round->isVoting() || $round->isCompleted()) {
                $response['answers'] = $round->answers()->with('player')->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'player_id' => $a->player_id,
                    'player_name' => $a->author_nickname ?? $a->player->nickname,
                    'text' => $a->text,
                    'votes_count' => $round->isCompleted() ? $a->votes_count : null,
                ]);

                // Check if current player has voted
                $myVote = $round->answers()
                    ->whereHas('votes', fn ($q) => $q->where('voter_id', $player->id))
                    ->first();
                if ($myVote) {
                    $response['my_vote'] = ['answer_id' => $myVote->id];
                }
            }
        }

        // Include completed rounds results
        $completedRounds = $game->rounds()
            ->where('status', 'completed')
            ->with(['answers.player'])
            ->orderBy('round_number')
            ->get()
            ->map(fn ($r) => [
                'round_number' => $r->round_number,
                'acronym' => $r->acronym,
                'answers' => $r->answers->sortByDesc('votes_count')->values()->map(fn ($a) => [
                    'player_name' => $a->author_nickname ?? $a->player->nickname,
                    'text' => $a->text,
                    'votes_count' => $a->votes_count,
                ]),
            ]);

        if ($completedRounds->isNotEmpty()) {
            $response['completed_rounds'] = $completedRounds;
        }

        return response()->json($response);
    }

    public function chat(Request $request, string $code, GetGameByCodeAction $getGame): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        // Rate limit: 1 message per 2 seconds per player
        $rateLimitKey = 'chat:'.$player->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json(['error' => 'Too many messages. Wait a moment.'], 429);
        }
        RateLimiter::hit($rateLimitKey, 2);

        $request->validate([
            'message' => 'required|string|max:200',
        ]);

        $message = $request->input('message');

        try {
            broadcast(new ChatMessageBroadcast($game, $player->nickname, $message))->toOthers();
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: chat.message', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'sent' => true,
            'message' => [
                'nickname' => $player->nickname,
                'message' => $message,
            ],
        ]);
    }

    private function formatGame($game): array
    {
        $players = $game->activePlayers()->get()->map(fn ($p) => [
            'id' => $p->id,
            'nickname' => $p->nickname,
            'score' => $p->pivot->score,
            'is_host' => $p->id === $game->host_player_id,
        ]);

        return [
            'id' => $game->id,
            'code' => $game->code,
            'status' => $game->status,
            'host_player_id' => $game->host_player_id,
            'current_round' => $game->current_round,
            'total_rounds' => $game->total_rounds,
            'settings' => $game->settings,
            'is_public' => $game->is_public,
            'has_password' => ! is_null($game->password),
            'players' => $players,
        ];
    }
}
