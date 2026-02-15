<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Application\Broadcasting\Events\ChatMessageBroadcast;
use App\Application\Broadcasting\Events\CoHostChangedBroadcast;
use App\Application\Broadcasting\Events\GameRematchBroadcast;
use App\Application\Broadcasting\Events\GameSettingsChangedBroadcast;
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
use App\Infrastructure\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::publicJoinable()
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
                'status' => $game->status,
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
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
            broadcast(new GameStartedBroadcast($game))->toOthers();
            broadcast(new RoundStartedBroadcast($game, $round))->toOthers();
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

        $round = $game->currentRoundModel();
        $response = [
            'game' => $this->formatGame($game),
            'phase' => $this->derivePhase($game, $round),
        ];

        // Include current round info if game is active
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
            'action' => 'sometimes|boolean',
        ]);

        $message = $request->input('message');
        $isAction = $request->boolean('action');

        try {
            $broadcast = new ChatMessageBroadcast($game, $player->nickname, $message);
            $broadcast->action = $isAction;
            broadcast($broadcast)->toOthers();
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: chat.message', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'sent' => true,
            'message' => [
                'nickname' => $player->nickname,
                'message' => $message,
                'action' => $isAction,
            ],
        ]);
    }

    public function toggleCoHost(Request $request, string $code, string $playerId, GetGameByCodeAction $getGame): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        if ($game->host_player_id !== $player->id) {
            return response()->json(['error' => 'Only the host can manage co-hosts'], 403);
        }

        if ($playerId === $player->id) {
            return response()->json(['error' => 'Cannot change your own co-host status'], 422);
        }

        $gamePlayer = $game->gamePlayers()
            ->where('player_id', $playerId)
            ->where('is_active', true)
            ->first();

        if (! $gamePlayer) {
            return response()->json(['error' => 'Player not found in this game'], 404);
        }

        $gamePlayer->update(['is_co_host' => ! $gamePlayer->is_co_host]);

        try {
            broadcast(new CoHostChangedBroadcast($game, $playerId, $gamePlayer->is_co_host));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: co_host.changed', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'player_id' => $playerId,
            'is_co_host' => $gamePlayer->is_co_host,
        ]);
    }

    public function rematch(Request $request, string $code, GetGameByCodeAction $getGame, CreateGameAction $createAction, JoinGameAction $joinAction): JsonResponse
    {
        $player = $request->attributes->get('player');
        $oldGame = $getGame->execute($code);

        if (! $oldGame) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        if (! $oldGame->isFinished()) {
            return response()->json(['error' => 'Game is not finished'], 422);
        }

        if (! $oldGame->isHostOrCoHost($player)) {
            return response()->json(['error' => 'Only host or co-host can start a rematch'], 403);
        }

        // Create new game with same settings
        $newGame = $createAction->execute(
            $player,
            $oldGame->settings,
            $oldGame->is_public,
        );

        // Auto-join all other active players from the old game
        $otherPlayers = $oldGame->activePlayers()->where('players.id', '!=', $player->id)->get();
        foreach ($otherPlayers as $otherPlayer) {
            try {
                $joinAction->execute($newGame, $otherPlayer);
            } catch (\Throwable $e) {
                Log::warning('Rematch: Failed to auto-join player', [
                    'player_id' => $otherPlayer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Broadcast rematch to old game channel so all clients redirect
        try {
            broadcast(new GameRematchBroadcast($oldGame, $newGame->code));
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: game.rematch', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'game' => $this->formatGame($newGame->fresh()),
        ]);
    }

    public function updateVisibility(Request $request, string $code, GetGameByCodeAction $getGame): JsonResponse
    {
        $player = $request->attributes->get('player');
        $game = $getGame->execute($code);

        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        if (! $game->isHostOrCoHost($player)) {
            return response()->json(['error' => 'Only host or co-host can change visibility'], 403);
        }

        $request->validate([
            'is_public' => 'required|boolean',
        ]);

        $game->update(['is_public' => $request->boolean('is_public')]);

        try {
            broadcast(new GameSettingsChangedBroadcast($game, [
                'is_public' => $game->is_public,
            ]))->toOthers();
        } catch (\Throwable $e) {
            Log::error('Broadcast failed: game.settings_changed', ['game' => $code, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'is_public' => $game->is_public,
        ]);
    }

    private function derivePhase(Game $game, ?Round $round): string
    {
        if ($game->isInLobby()) {
            return 'lobby';
        }

        if ($game->isFinished()) {
            return 'finished';
        }

        if (! $round) {
            return 'results';
        }

        return match ($round->status) {
            'answering' => 'playing',
            'voting' => 'voting',
            'completed' => 'results',
            default => 'playing',
        };
    }

    private function formatGame($game): array
    {
        $players = $game->activePlayers()->get()->map(fn ($p) => [
            'id' => $p->id,
            'nickname' => $p->nickname,
            'score' => $p->pivot->score,
            'is_host' => $p->id === $game->host_player_id,
            'is_co_host' => (bool) $p->pivot->is_co_host,
        ]);

        $result = [
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

        // Include winner info for finished games
        if ($game->isFinished()) {
            $gameResult = $game->gameResult;
            if ($gameResult) {
                $winner = collect($gameResult->final_scores)->firstWhere('is_winner', true);
                $result['winner'] = $winner;
            }
        }

        return $result;
    }
}
