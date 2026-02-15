<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Models\GameResult;
use App\Infrastructure\Models\HallOfFame;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\PlayerStat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController extends Controller
{
    /**
     * Resolve a nickname to either a User or a Player (guest/bot).
     *
     * @return array{user: ?User, player: ?Player, nickname: string}
     */
    private function resolvePlayer(string $nickname): array
    {
        $user = User::where('nickname', $nickname)->first();
        if ($user) {
            return ['user' => $user, 'player' => null, 'nickname' => $user->nickname];
        }

        $player = Player::where('nickname', $nickname)->first();
        if ($player) {
            return ['user' => null, 'player' => $player, 'nickname' => $player->nickname];
        }

        return ['user' => null, 'player' => null, 'nickname' => $nickname];
    }

    public function show(string $nickname): JsonResponse
    {
        $resolved = $this->resolvePlayer($nickname);

        if (! $resolved['user'] && ! $resolved['player']) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $playerInfo = [];
        $stat = null;
        $recentWins = collect();

        if ($resolved['user']) {
            $user = $resolved['user'];
            $stat = PlayerStat::where('user_id', $user->id)->first();

            $recentWins = HallOfFame::where('author_user_id', $user->id)
                ->where('is_round_winner', true)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            $playerInfo = [
                'nickname' => $user->nickname,
                'avatar_url' => $user->avatar_url,
                'member_since' => $user->created_at?->toIso8601String(),
                'is_bot' => false,
                'is_guest' => false,
            ];
        } else {
            $player = $resolved['player'];

            $recentWins = HallOfFame::where('author_nickname', $player->nickname)
                ->where('is_round_winner', true)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            $playerInfo = [
                'nickname' => $player->nickname,
                'avatar_url' => null,
                'member_since' => $player->created_at?->toIso8601String(),
                'is_bot' => $player->is_bot,
                'is_guest' => $player->is_guest,
            ];
        }

        return response()->json([
            'player' => $playerInfo,
            'stats' => $stat ? [
                'games_played' => $stat->games_played,
                'games_won' => $stat->games_won,
                'win_rate' => $stat->win_rate,
                'rounds_played' => $stat->rounds_played,
                'rounds_won' => $stat->rounds_won,
                'votes_received' => $stat->total_votes_received,
                'total_sentences_submitted' => $stat->total_sentences_submitted,
                'best_sentence' => $stat->best_sentence,
                'best_sentence_votes' => $stat->best_sentence_votes,
            ] : null,
            'recent_wins' => $recentWins->map(fn ($entry) => [
                'acronym' => $entry->acronym,
                'sentence' => $entry->sentence,
                'votes_count' => $entry->votes_count,
                'game_code' => $entry->game_code,
                'played_at' => $entry->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function stats(string $nickname): JsonResponse
    {
        $resolved = $this->resolvePlayer($nickname);

        if (! $resolved['user'] && ! $resolved['player']) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $stat = null;
        if ($resolved['user']) {
            $stat = PlayerStat::where('user_id', $resolved['user']->id)->first();
        }

        return response()->json([
            'stats' => $stat ? [
                'games_played' => $stat->games_played,
                'games_won' => $stat->games_won,
                'win_rate' => $stat->win_rate,
                'rounds_played' => $stat->rounds_played,
                'rounds_won' => $stat->rounds_won,
                'votes_received' => $stat->total_votes_received,
                'total_sentences_submitted' => $stat->total_sentences_submitted,
                'best_sentence' => $stat->best_sentence,
                'best_sentence_votes' => $stat->best_sentence_votes,
            ] : null,
        ]);
    }

    public function sentences(string $nickname, Request $request): JsonResponse
    {
        $resolved = $this->resolvePlayer($nickname);

        if (! $resolved['user'] && ! $resolved['player']) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $limit = min((int) $request->query('limit', 20), 50);

        $query = HallOfFame::query()->orderByDesc('votes_count')->limit($limit);

        if ($resolved['user']) {
            $query->where('author_user_id', $resolved['user']->id);
        } else {
            $query->where('author_nickname', $resolved['nickname']);
        }

        $sentences = $query->get()->map(fn ($entry) => [
            'id' => $entry->id,
            'acronym' => $entry->acronym,
            'text' => $entry->sentence,
            'votes_count' => $entry->votes_count,
            'game_code' => $entry->game_code,
            'is_round_winner' => $entry->is_round_winner,
            'played_at' => $entry->created_at?->toIso8601String(),
        ]);

        return response()->json(['sentences' => $sentences]);
    }

    public function games(string $nickname, Request $request): JsonResponse
    {
        $resolved = $this->resolvePlayer($nickname);

        if (! $resolved['user'] && ! $resolved['player']) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $limit = min((int) $request->query('limit', 20), 50);
        $lookupName = $resolved['nickname'];

        $games = GameResult::query()
            ->with('game:id,code,finished_at')
            ->whereJsonContains('final_scores', [['player_name' => $lookupName]])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($result) use ($lookupName) {
                $playerScore = collect($result->final_scores)
                    ->firstWhere('player_name', $lookupName);

                $rank = collect($result->final_scores)
                    ->sortByDesc('score')
                    ->values()
                    ->search(fn ($s) => $s['player_name'] === $lookupName);

                return [
                    'code' => $result->game?->code,
                    'score' => $playerScore['score'] ?? 0,
                    'is_winner' => $playerScore['is_winner'] ?? false,
                    'placement' => $rank !== false ? '#'.($rank + 1) : null,
                    'player_count' => $result->player_count,
                    'finished_at' => $result->game?->finished_at?->toIso8601String(),
                ];
            });

        return response()->json(['games' => $games]);
    }
}
