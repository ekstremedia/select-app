<?php

namespace Database\Seeders;

use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GameResult;
use App\Infrastructure\Models\HallOfFame;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\PlayerStat;
use App\Infrastructure\Models\Round;
use App\Infrastructure\Models\Vote;
use Illuminate\Database\Seeder;

class FinishedGameSeeder extends Seeder
{
    private array $sampleAcronyms = ['TIHWP', 'BSNG', 'FKDM', 'WRTL', 'HNSV', 'GLPF', 'DSKM', 'TRBW'];

    private array $sampleSentences = [
        'TIHWP' => ['This Is How We Play', 'Today I Had Wonderful Pizza', 'Tigers In Hawaii Were Peaceful'],
        'BSNG' => ['Boys Singing Nice Guitar', 'Big Snakes Need Glasses', 'Better Start Nothing Good'],
        'FKDM' => ['Friendly Kids Dance Madly', 'Fish Know Deep Mysteries', 'Four Kings Drive Mercedes'],
        'WRTL' => ['We Really Try Lazily', 'Whales Run Through Lakes', 'Worms Rule The Land'],
        'HNSV' => ['Hot Nights Stay Vivid', 'Horses Never Seem Violent', 'Hippos Nap So Vividly'],
        'GLPF' => ['Girls Love Playing Football', 'Great Lions Prowl Fast', 'Green Leaves Pull Free'],
        'DSKM' => ['Dogs Sleep Knowing More', 'Dark Skies Keep Moving', 'Dancing Snakes Kill Mice'],
        'TRBW' => ['Totally Random But Wonderful', 'Trees Rise Before Winter', 'Three Rats Brought Wine'],
    ];

    public function run(): void
    {
        $this->call(UserSeeder::class);

        $players = Player::all();
        if ($players->count() < 2) {
            $this->command->warn('Need at least 2 players. Run UserSeeder first.');

            return;
        }

        // Create some guest players
        $guestNicknames = ['Viking', 'Troll', 'Nansen', 'Gransen', 'Fjansen', 'Kansen'];
        foreach ($guestNicknames as $nickname) {
            Player::firstOrCreate(
                ['nickname' => $nickname],
                [
                    'guest_token' => bin2hex(random_bytes(16)),
                    'is_guest' => true,
                    'last_active_at' => now()->subHours(rand(1, 48)),
                ],
            );
        }

        $allPlayers = Player::all();

        // Create 5 finished games
        for ($g = 0; $g < 5; $g++) {
            $this->createFinishedGame($allPlayers, $g);
        }

        $this->command->info('Created 5 finished games with archive data.');
    }

    private function createFinishedGame($allPlayers, int $index): void
    {
        $playerCount = rand(3, min(6, $allPlayers->count()));
        $gamePlayers = $allPlayers->random($playerCount);
        $host = $gamePlayers->first();
        $roundCount = rand(3, 5);

        $startedAt = now()->subDays(rand(0, 14))->subHours(rand(0, 12));
        $durationSeconds = $roundCount * rand(120, 240);

        $game = Game::create([
            'code' => strtoupper(substr(md5("game-{$index}"), 0, 6)),
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => array_merge((new Game)->getDefaultSettings(), ['rounds' => $roundCount]),
            'total_rounds' => $roundCount,
            'current_round' => $roundCount,
            'is_public' => true,
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ]);

        // Add players to game
        $scores = [];
        foreach ($gamePlayers as $player) {
            $scores[$player->id] = 0;
            $game->gamePlayers()->create([
                'player_id' => $player->id,
                'score' => 0,
                'joined_at' => $startedAt,
            ]);
        }

        // Create rounds
        for ($r = 1; $r <= $roundCount; $r++) {
            $acronymIndex = ($index * $roundCount + $r) % count($this->sampleAcronyms);
            $acronym = $this->sampleAcronyms[$acronymIndex];

            $round = Round::create([
                'game_id' => $game->id,
                'round_number' => $r,
                'acronym' => $acronym,
                'status' => Round::STATUS_COMPLETED,
                'answer_deadline' => $startedAt->copy()->addSeconds($r * 120),
                'vote_deadline' => $startedAt->copy()->addSeconds($r * 120 + 60),
            ]);

            // Create answers
            $roundAnswers = [];
            $sentences = $this->sampleSentences[$acronym] ?? ['Sample Answer Words Here'];

            foreach ($gamePlayers as $i => $player) {
                $sentence = $sentences[$i % count($sentences)] ?? "Answer {$i} For {$acronym}";
                $answer = Answer::create([
                    'round_id' => $round->id,
                    'player_id' => $player->id,
                    'text' => $sentence,
                    'author_nickname' => $player->nickname,
                    'votes_count' => 0,
                ]);
                $roundAnswers[] = $answer;
            }

            // Create votes (each player votes for someone else)
            foreach ($gamePlayers as $voter) {
                $votableAnswers = collect($roundAnswers)->where('player_id', '!=', $voter->id);
                if ($votableAnswers->isEmpty()) {
                    continue;
                }
                $votedAnswer = $votableAnswers->random();

                Vote::create([
                    'answer_id' => $votedAnswer->id,
                    'voter_id' => $voter->id,
                    'voter_nickname' => $voter->nickname,
                ]);

                $votedAnswer->increment('votes_count');
                $scores[$votedAnswer->player_id] = ($scores[$votedAnswer->player_id] ?? 0) + 1;
            }

            // Save hall of fame entries
            $roundWinner = collect($roundAnswers)->sortByDesc('votes_count')->first();
            foreach ($roundAnswers as $answer) {
                $answer->refresh();
                if ($answer->votes_count <= 0) {
                    continue;
                }

                $voterNicknames = $answer->votes->map(fn ($v) => $v->voter_nickname)->filter()->values()->toArray();
                HallOfFame::create([
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'round_number' => $r,
                    'acronym' => $acronym,
                    'sentence' => $answer->text,
                    'author_nickname' => $answer->author_nickname,
                    'author_user_id' => $answer->player->user_id,
                    'votes_count' => $answer->votes_count,
                    'voter_nicknames' => $voterNicknames,
                    'is_round_winner' => $roundWinner && $roundWinner->id === $answer->id,
                ]);
            }
        }

        // Update game player scores
        foreach ($scores as $playerId => $score) {
            $game->gamePlayers()->where('player_id', $playerId)->update(['score' => $score]);
        }

        // Create game result
        $finalScores = $game->gamePlayers()
            ->with('player')
            ->orderByDesc('score')
            ->get()
            ->map(fn ($gp, $i) => [
                'player_id' => $gp->player_id,
                'player_name' => $gp->player->nickname,
                'score' => $gp->score,
                'is_winner' => $i === 0,
            ])
            ->toArray();

        $winner = $finalScores[0] ?? null;

        GameResult::create([
            'game_id' => $game->id,
            'winner_nickname' => $winner['player_name'] ?? null,
            'winner_user_id' => $winner ? $game->gamePlayers()->orderByDesc('score')->first()?->player?->user_id : null,
            'final_scores' => $finalScores,
            'rounds_played' => $roundCount,
            'player_count' => $playerCount,
            'duration_seconds' => $durationSeconds,
        ]);

        // Update player stats for registered users
        foreach ($gamePlayers as $player) {
            if (! $player->user_id) {
                continue;
            }

            $stat = PlayerStat::firstOrCreate(
                ['user_id' => $player->user_id],
                [
                    'games_played' => 0, 'games_won' => 0, 'rounds_played' => 0, 'rounds_won' => 0,
                    'total_votes_received' => 0, 'total_sentences_submitted' => 0,
                    'best_sentence' => null, 'best_sentence_votes' => 0, 'win_rate' => 0,
                ],
            );
            $stat->increment('games_played');
            if ($winner && $player->id === $winner['player_id']) {
                $stat->increment('games_won');
            }
            $stat->refresh();
            $stat->recalculateWinRate();
        }

        // Update player model stats
        $player = Player::find($winner['player_id'] ?? null);
        if ($player) {
            $player->increment('games_won');
        }
        foreach ($gamePlayers as $p) {
            $p->increment('games_played');
            $p->increment('total_score', $scores[$p->id] ?? 0);
        }
    }
}
