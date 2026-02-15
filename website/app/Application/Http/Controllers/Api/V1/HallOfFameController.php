<?php

namespace App\Application\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Models\HallOfFame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HallOfFameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HallOfFame::query()
            ->where('is_round_winner', true)
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at');

        if ($request->query('acronym')) {
            $query->where('acronym', strtoupper($request->query('acronym')));
        }

        $entries = $query->paginate(30);

        $entries->getCollection()->transform(fn ($entry) => [
            'id' => $entry->id,
            'acronym' => $entry->acronym,
            'sentence' => $entry->sentence,
            'author_nickname' => $entry->author_nickname,
            'votes_count' => $entry->votes_count,
            'voter_nicknames' => $entry->voter_nicknames,
            'game_code' => $entry->game_code,
            'round_number' => $entry->round_number,
            'played_at' => $entry->created_at?->toIso8601String(),
        ]);

        return response()->json($entries);
    }

    public function random(): JsonResponse
    {
        // Try to get a random classic sentence from gullkorn_clean (5+ votes, 3-6 words)
        $driver = DB::connection()->getDriverName();
        $wordCountFilter = $driver === 'pgsql'
            ? "array_length(regexp_split_to_array(trim(setning), E'\\\\s+'), 1) BETWEEN 3 AND 6"
            : "length(trim(setning)) - length(replace(trim(setning), ' ', '')) BETWEEN 2 AND 5";

        $gullkorn = DB::table('gullkorn_clean')
            ->where('stemmer', '>', 4)
            ->whereRaw($wordCountFilter)
            ->inRandomOrder()
            ->first();

        if ($gullkorn) {
            return response()->json([
                'sentence' => [
                    'text' => $gullkorn->setning,
                    'author' => $gullkorn->nick,
                    'votes' => $gullkorn->stemmer,
                    'source' => 'irc',
                ],
            ]);
        }

        // Fallback to hall of fame
        $entry = HallOfFame::where('is_round_winner', true)
            ->inRandomOrder()
            ->first();

        if (! $entry) {
            return response()->json(['sentence' => null]);
        }

        return response()->json([
            'sentence' => [
                'text' => $entry->sentence,
                'author' => $entry->author_nickname,
                'votes' => $entry->votes_count,
                'acronym' => $entry->acronym,
                'source' => 'game',
            ],
        ]);
    }
}
