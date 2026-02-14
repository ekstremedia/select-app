<?php

namespace Tests\Feature\Api;

use App\Application\Jobs\ProcessAnswerDeadlineJob;
use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\GamePlayer;
use App\Infrastructure\Models\GameResult;
use App\Infrastructure\Models\HallOfFame;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\PlayerStat;
use App\Infrastructure\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessAnswerDeadlineJob::class]);

        // Create the gullkorn_clean table for tests (not managed by migrations)
        DB::statement('CREATE TABLE IF NOT EXISTS gullkorn_clean (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nick VARCHAR(9) NOT NULL DEFAULT \'\',
            setning TEXT NOT NULL,
            stemmer INTEGER NOT NULL DEFAULT 0,
            tid TIMESTAMP NULL,
            hvemstemte TEXT NOT NULL DEFAULT \'\'
        )');
    }

    /**
     * Helper to create a finished game with a GameResult for archive tests.
     */
    private function createFinishedGameWithResult(string $winnerNickname = 'Winner', int $playerCount = 2, int $roundsPlayed = 3): array
    {
        $host = Player::factory()->create(['nickname' => $winnerNickname]);

        $game = Game::create([
            'code' => strtoupper(substr(md5(uniqid()), 0, 6)),
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => $roundsPlayed,
            'current_round' => $roundsPlayed,
            'is_public' => false,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'duration_seconds' => 600,
        ]);

        // Add host as game player
        GamePlayer::create([
            'game_id' => $game->id,
            'player_id' => $host->id,
            'score' => 10,
            'is_active' => true,
            'joined_at' => now()->subMinutes(15),
        ]);

        // Add additional players
        $players = [$host];
        for ($i = 1; $i < $playerCount; $i++) {
            $player = Player::factory()->create(['nickname' => "Player{$i}"]);
            GamePlayer::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'score' => 10 - ($i * 2),
                'is_active' => true,
                'joined_at' => now()->subMinutes(15),
            ]);
            $players[] = $player;
        }

        // Create rounds with answers
        for ($r = 1; $r <= $roundsPlayed; $r++) {
            $round = Round::create([
                'game_id' => $game->id,
                'round_number' => $r,
                'acronym' => 'ABC',
                'status' => Round::STATUS_COMPLETED,
                'answer_deadline' => now()->subMinutes(5),
                'vote_deadline' => now()->subMinutes(3),
            ]);

            foreach ($players as $player) {
                Answer::create([
                    'round_id' => $round->id,
                    'player_id' => $player->id,
                    'text' => "A Big Cat from {$player->nickname}",
                    'author_nickname' => $player->nickname,
                    'votes_count' => $player->id === $host->id ? 1 : 0,
                ]);
            }
        }

        $finalScores = collect($players)->map(fn ($p, $i) => [
            'nickname' => $p->nickname,
            'score' => 10 - ($i * 2),
        ])->values()->toArray();

        $result = GameResult::create([
            'game_id' => $game->id,
            'winner_nickname' => $winnerNickname,
            'final_scores' => $finalScores,
            'rounds_played' => $roundsPlayed,
            'player_count' => $playerCount,
            'duration_seconds' => 600,
        ]);

        return ['game' => $game, 'result' => $result, 'players' => $players];
    }

    public function test_archive_list_returns_paginated_results(): void
    {
        // Create 3 finished games with results
        $this->createFinishedGameWithResult('WinnerA');
        $this->createFinishedGameWithResult('WinnerB');
        $this->createFinishedGameWithResult('WinnerC');

        $response = $this->getJson('/api/v1/archive');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'winner_nickname',
                        'rounds_played',
                        'player_count',
                        'duration_seconds',
                        'final_scores',
                        'played_at',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertEquals(3, $response->json('total'));
    }

    public function test_archive_show_returns_game_detail(): void
    {
        $data = $this->createFinishedGameWithResult('DetailWinner', 3, 2);
        $game = $data['game'];

        $response = $this->getJson("/api/v1/archive/{$game->code}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'game' => [
                    'code',
                    'status',
                    'settings',
                    'started_at',
                    'finished_at',
                    'duration_seconds',
                ],
                'players' => [
                    '*' => [
                        'nickname',
                        'score',
                        'rank',
                    ],
                ],
                'rounds' => [
                    '*' => [
                        'round_number',
                        'acronym',
                        'answers' => [
                            '*' => [
                                'player_name',
                                'text',
                                'votes_count',
                                'voters',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertEquals('finished', $response->json('game.status'));
        $this->assertCount(3, $response->json('players'));
        $this->assertCount(2, $response->json('rounds'));

        // Verify players are sorted by score descending
        $players = $response->json('players');
        $this->assertEquals(1, $players[0]['rank']);
        $this->assertGreaterThanOrEqual($players[1]['score'], $players[0]['score']);
    }

    public function test_leaderboard_returns_sorted_stats(): void
    {
        // Create users with player stats
        $user1 = User::factory()->create(['nickname' => 'TopPlayer']);
        $user2 = User::factory()->create(['nickname' => 'SecondPlayer']);
        $user3 = User::factory()->create(['nickname' => 'ThirdPlayer']);

        PlayerStat::create([
            'user_id' => $user1->id,
            'games_played' => 20,
            'games_won' => 15,
            'rounds_played' => 100,
            'rounds_won' => 50,
            'total_votes_received' => 200,
            'total_sentences_submitted' => 100,
            'best_sentence' => 'Amazing Best Comeback',
            'best_sentence_votes' => 5,
            'win_rate' => 75.00,
        ]);

        PlayerStat::create([
            'user_id' => $user2->id,
            'games_played' => 10,
            'games_won' => 8,
            'rounds_played' => 50,
            'rounds_won' => 30,
            'total_votes_received' => 100,
            'total_sentences_submitted' => 50,
            'best_sentence' => 'Best Sentence Ever',
            'best_sentence_votes' => 4,
            'win_rate' => 80.00,
        ]);

        PlayerStat::create([
            'user_id' => $user3->id,
            'games_played' => 5,
            'games_won' => 1,
            'rounds_played' => 25,
            'rounds_won' => 5,
            'total_votes_received' => 20,
            'total_sentences_submitted' => 25,
            'best_sentence' => 'Cool Down Easy',
            'best_sentence_votes' => 2,
            'win_rate' => 20.00,
        ]);

        $response = $this->getJson('/api/v1/leaderboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'leaderboard' => [
                    '*' => [
                        'rank',
                        'nickname',
                        'games_played',
                        'games_won',
                        'win_rate',
                        'rounds_played',
                        'rounds_won',
                        'total_votes_received',
                        'total_sentences_submitted',
                        'best_sentence',
                        'best_sentence_votes',
                    ],
                ],
            ]);

        $leaderboard = $response->json('leaderboard');
        $this->assertCount(3, $leaderboard);

        // Default sort is by games_won desc
        $this->assertEquals('TopPlayer', $leaderboard[0]['nickname']);
        $this->assertEquals(15, $leaderboard[0]['games_won']);
        $this->assertEquals('SecondPlayer', $leaderboard[1]['nickname']);
        $this->assertEquals('ThirdPlayer', $leaderboard[2]['nickname']);
    }

    public function test_leaderboard_supports_sort_parameter(): void
    {
        $user1 = User::factory()->create(['nickname' => 'HighWinRate']);
        $user2 = User::factory()->create(['nickname' => 'LowWinRate']);

        PlayerStat::create([
            'user_id' => $user1->id,
            'games_played' => 5,
            'games_won' => 4,
            'rounds_played' => 25,
            'rounds_won' => 20,
            'total_votes_received' => 50,
            'total_sentences_submitted' => 25,
            'win_rate' => 80.00,
        ]);

        PlayerStat::create([
            'user_id' => $user2->id,
            'games_played' => 20,
            'games_won' => 5,
            'rounds_played' => 100,
            'rounds_won' => 10,
            'total_votes_received' => 100,
            'total_sentences_submitted' => 100,
            'win_rate' => 25.00,
        ]);

        // Sort by win_rate
        $response = $this->getJson('/api/v1/leaderboard?sort=win_rate');

        $response->assertStatus(200);

        $leaderboard = $response->json('leaderboard');
        $this->assertEquals('HighWinRate', $leaderboard[0]['nickname']);
        $this->assertEquals('LowWinRate', $leaderboard[1]['nickname']);

        // Sort by games_played
        $response = $this->getJson('/api/v1/leaderboard?sort=games_played');

        $response->assertStatus(200);

        $leaderboard = $response->json('leaderboard');
        $this->assertEquals('LowWinRate', $leaderboard[0]['nickname']);
        $this->assertEquals(20, $leaderboard[0]['games_played']);
    }

    public function test_hall_of_fame_returns_winning_sentences(): void
    {
        // We need a game to satisfy the foreign key
        $host = Player::factory()->create(['nickname' => 'FameHost']);
        $game = Game::create([
            'code' => 'FAME01',
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => 3,
            'is_public' => false,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 1,
            'acronym' => 'ABC',
            'sentence' => 'Amazing Best Comeback',
            'author_nickname' => 'FameHost',
            'votes_count' => 5,
            'voter_nicknames' => ['Voter1', 'Voter2', 'Voter3', 'Voter4', 'Voter5'],
            'is_round_winner' => true,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 2,
            'acronym' => 'DEF',
            'sentence' => 'Daring Escape Forward',
            'author_nickname' => 'FameHost',
            'votes_count' => 3,
            'voter_nicknames' => ['Voter1', 'Voter2', 'Voter3'],
            'is_round_winner' => true,
        ]);

        // Create a non-winner entry that should be excluded
        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 3,
            'acronym' => 'GHI',
            'sentence' => 'Going Home Immediately',
            'author_nickname' => 'OtherPlayer',
            'votes_count' => 1,
            'voter_nicknames' => ['Voter1'],
            'is_round_winner' => false,
        ]);

        $response = $this->getJson('/api/v1/hall-of-fame');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'acronym',
                        'sentence',
                        'author_nickname',
                        'votes_count',
                        'voter_nicknames',
                        'game_code',
                        'round_number',
                        'played_at',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        // Only round winners should appear
        $this->assertEquals(2, $response->json('total'));

        // Sorted by votes_count descending
        $data = $response->json('data');
        $this->assertEquals(5, $data[0]['votes_count']);
        $this->assertEquals('Amazing Best Comeback', $data[0]['sentence']);
    }

    public function test_player_profile_returns_stats(): void
    {
        $user = User::factory()->create(['nickname' => 'ProfilePlayer']);

        PlayerStat::create([
            'user_id' => $user->id,
            'games_played' => 15,
            'games_won' => 10,
            'rounds_played' => 75,
            'rounds_won' => 40,
            'total_votes_received' => 150,
            'total_sentences_submitted' => 75,
            'best_sentence' => 'Perfect Quality Response',
            'best_sentence_votes' => 6,
            'win_rate' => 66.67,
        ]);

        // Create a finished game for hall of fame entries
        $host = Player::factory()->create(['nickname' => 'SomeHost']);
        $game = Game::create([
            'code' => 'PROF01',
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => 3,
            'is_public' => false,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 1,
            'acronym' => 'PQR',
            'sentence' => 'Perfect Quality Response',
            'author_nickname' => 'ProfilePlayer',
            'author_user_id' => $user->id,
            'votes_count' => 6,
            'voter_nicknames' => ['V1', 'V2', 'V3', 'V4', 'V5', 'V6'],
            'is_round_winner' => true,
        ]);

        $response = $this->getJson('/api/v1/players/ProfilePlayer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'player' => [
                    'nickname',
                    'avatar_url',
                    'member_since',
                ],
                'stats' => [
                    'games_played',
                    'games_won',
                    'win_rate',
                    'rounds_played',
                    'rounds_won',
                    'votes_received',
                    'total_sentences_submitted',
                    'best_sentence',
                    'best_sentence_votes',
                ],
                'recent_wins',
            ]);

        $this->assertEquals('ProfilePlayer', $response->json('player.nickname'));
        $this->assertEquals(15, $response->json('stats.games_played'));
        $this->assertEquals(10, $response->json('stats.games_won'));
        $this->assertEquals('Perfect Quality Response', $response->json('stats.best_sentence'));

        // Verify recent wins include the hall of fame entry
        $this->assertCount(1, $response->json('recent_wins'));
        $this->assertEquals('PQR', $response->json('recent_wins.0.acronym'));
    }

    public function test_player_profile_returns_404_for_unknown_player(): void
    {
        $response = $this->getJson('/api/v1/players/NonExistentPlayer');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Player not found']);
    }

    public function test_archive_show_returns_404_for_invalid_game(): void
    {
        $response = $this->getJson('/api/v1/archive/XXXXXX');

        $response->assertStatus(404);
    }

    // --- Hall of Fame Random ---

    public function test_hall_of_fame_random_returns_sentence_from_hall_of_fame(): void
    {
        $host = Player::factory()->create(['nickname' => 'RandomHost']);
        $game = Game::create([
            'code' => 'RAND01',
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => 1,
            'is_public' => false,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 1,
            'acronym' => 'XYZ',
            'sentence' => 'Xtra Yummy Zucchini',
            'author_nickname' => 'RandomHost',
            'votes_count' => 3,
            'voter_nicknames' => ['V1', 'V2', 'V3'],
            'is_round_winner' => true,
        ]);

        $response = $this->getJson('/api/v1/hall-of-fame/random');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sentence' => [
                    'text',
                    'author',
                    'votes',
                    'source',
                ],
            ]);

        $source = $response->json('sentence.source');
        $this->assertContains($source, ['irc', 'game']);
    }

    public function test_hall_of_fame_random_returns_null_when_no_data(): void
    {
        $response = $this->getJson('/api/v1/hall-of-fame/random');

        $response->assertStatus(200)
            ->assertJson(['sentence' => null]);
    }

    public function test_hall_of_fame_random_returns_gullkorn_when_available(): void
    {
        DB::table('gullkorn_clean')->insert([
            'nick' => 'IRCPlayer',
            'setning' => 'Totally Insane Random Comment',
            'stemmer' => 7,
            'hvemstemte' => 'voter1,voter2',
        ]);

        $response = $this->getJson('/api/v1/hall-of-fame/random');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sentence' => [
                    'text',
                    'author',
                    'votes',
                    'source',
                ],
            ]);

        $this->assertEquals('irc', $response->json('sentence.source'));
        $this->assertEquals('Totally Insane Random Comment', $response->json('sentence.text'));
        $this->assertEquals('IRCPlayer', $response->json('sentence.author'));
        $this->assertEquals(7, $response->json('sentence.votes'));
    }

    // --- Player Sub-Endpoints ---

    public function test_player_stats_endpoint_returns_stats(): void
    {
        $user = User::factory()->create(['nickname' => 'StatsPlayer']);

        PlayerStat::create([
            'user_id' => $user->id,
            'games_played' => 12,
            'games_won' => 7,
            'rounds_played' => 60,
            'rounds_won' => 30,
            'total_votes_received' => 120,
            'total_sentences_submitted' => 60,
            'best_sentence' => 'Superb Tall Alpacas',
            'best_sentence_votes' => 4,
            'win_rate' => 58.33,
        ]);

        $response = $this->getJson('/api/v1/players/StatsPlayer/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'games_played',
                    'games_won',
                    'win_rate',
                    'rounds_played',
                    'rounds_won',
                    'votes_received',
                    'total_sentences_submitted',
                    'best_sentence',
                    'best_sentence_votes',
                ],
            ]);

        $this->assertEquals(12, $response->json('stats.games_played'));
        $this->assertEquals(7, $response->json('stats.games_won'));
        $this->assertEquals('Superb Tall Alpacas', $response->json('stats.best_sentence'));
    }

    public function test_player_stats_returns_null_stats_for_user_without_stats(): void
    {
        User::factory()->create(['nickname' => 'NoStatsPlayer']);

        $response = $this->getJson('/api/v1/players/NoStatsPlayer/stats');

        $response->assertStatus(200)
            ->assertJson(['stats' => null]);
    }

    public function test_player_stats_returns_404_for_unknown_player(): void
    {
        $response = $this->getJson('/api/v1/players/GhostPlayer/stats');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Player not found']);
    }

    public function test_player_sentences_returns_sentences(): void
    {
        $user = User::factory()->create(['nickname' => 'SentencePlayer']);

        $host = Player::factory()->create(['nickname' => 'SentHost']);
        $game = Game::create([
            'code' => 'SENT01',
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => 2,
            'is_public' => false,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 1,
            'acronym' => 'ABC',
            'sentence' => 'Always Be Creative',
            'author_nickname' => 'SentencePlayer',
            'author_user_id' => $user->id,
            'votes_count' => 4,
            'voter_nicknames' => ['V1', 'V2', 'V3', 'V4'],
            'is_round_winner' => true,
        ]);

        HallOfFame::create([
            'game_id' => $game->id,
            'game_code' => $game->code,
            'round_number' => 2,
            'acronym' => 'DEF',
            'sentence' => 'Daring Escape Friday',
            'author_nickname' => 'SentencePlayer',
            'author_user_id' => $user->id,
            'votes_count' => 2,
            'voter_nicknames' => ['V1', 'V2'],
            'is_round_winner' => false,
        ]);

        $response = $this->getJson('/api/v1/players/SentencePlayer/sentences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sentences' => [
                    '*' => [
                        'id',
                        'acronym',
                        'text',
                        'votes_count',
                        'game_code',
                        'is_round_winner',
                        'played_at',
                    ],
                ],
            ]);

        $sentences = $response->json('sentences');
        $this->assertCount(2, $sentences);

        // Sorted by votes_count descending
        $this->assertEquals(4, $sentences[0]['votes_count']);
        $this->assertEquals('Always Be Creative', $sentences[0]['text']);
    }

    public function test_player_sentences_returns_404_for_unknown_player(): void
    {
        $response = $this->getJson('/api/v1/players/GhostPlayer/sentences');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Player not found']);
    }

    public function test_player_sentences_returns_empty_for_user_without_sentences(): void
    {
        User::factory()->create(['nickname' => 'EmptySentPlayer']);

        $response = $this->getJson('/api/v1/players/EmptySentPlayer/sentences');

        $response->assertStatus(200)
            ->assertJson(['sentences' => []]);
    }

    public function test_player_games_endpoint_returns_200(): void
    {
        $user = User::factory()->create(['nickname' => 'GameHistPlayer']);

        $response = $this->getJson('/api/v1/players/GameHistPlayer/games');

        $response->assertStatus(200)
            ->assertJsonStructure(['games']);
    }

    /**
     * Tests game history with actual data. Uses whereJsonContains with nested
     * objects which requires PostgreSQL. Skipped on SQLite (test DB).
     */
    public function test_player_games_returns_game_history(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('whereJsonContains with nested objects requires PostgreSQL');
        }

        $user = User::factory()->create(['nickname' => 'GameHistPlayer']);

        $host = Player::factory()->create(['nickname' => 'GHHost']);
        $game = Game::create([
            'code' => 'GHIS01',
            'host_player_id' => $host->id,
            'status' => Game::STATUS_FINISHED,
            'settings' => (new Game)->getDefaultSettings(),
            'total_rounds' => 3,
            'is_public' => false,
            'finished_at' => now(),
        ]);

        GameResult::create([
            'game_id' => $game->id,
            'winner_nickname' => 'GameHistPlayer',
            'final_scores' => [
                ['player_name' => 'GameHistPlayer', 'score' => 10, 'is_winner' => true],
                ['player_name' => 'GHHost', 'score' => 5, 'is_winner' => false],
            ],
            'rounds_played' => 3,
            'player_count' => 2,
            'duration_seconds' => 300,
        ]);

        $response = $this->getJson('/api/v1/players/GameHistPlayer/games');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'games' => [
                    '*' => [
                        'code',
                        'score',
                        'is_winner',
                        'placement',
                        'player_count',
                        'finished_at',
                    ],
                ],
            ]);

        $games = $response->json('games');
        $this->assertCount(1, $games);
        $this->assertEquals('GHIS01', $games[0]['code']);
        $this->assertEquals(10, $games[0]['score']);
        $this->assertTrue($games[0]['is_winner']);
    }

    public function test_player_games_returns_404_for_unknown_player(): void
    {
        $response = $this->getJson('/api/v1/players/GhostPlayer/games');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Player not found']);
    }

    public function test_player_games_returns_empty_for_user_without_games(): void
    {
        User::factory()->create(['nickname' => 'NoGamePlayer']);

        $response = $this->getJson('/api/v1/players/NoGamePlayer/games');

        $response->assertStatus(200)
            ->assertJson(['games' => []]);
    }

    // --- Archive Filtering ---

    public function test_archive_filters_by_player_name(): void
    {
        $this->createFinishedGameWithResult('AlphaWinner');
        $this->createFinishedGameWithResult('BetaWinner');

        $response = $this->getJson('/api/v1/archive?player=Alpha');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('AlphaWinner', $response->json('data.0.winner_nickname'));
    }

    public function test_archive_filters_by_period_week(): void
    {
        // Create a recent game
        $this->createFinishedGameWithResult('RecentWinner');

        // Create an old game (manually update created_at)
        $oldData = $this->createFinishedGameWithResult('OldWinner');
        GameResult::where('game_id', $oldData['game']->id)
            ->update(['created_at' => now()->subDays(14)]);

        $response = $this->getJson('/api/v1/archive?period=week');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('RecentWinner', $response->json('data.0.winner_nickname'));
    }

    public function test_archive_filters_by_period_month(): void
    {
        // Create a recent game
        $this->createFinishedGameWithResult('MonthRecent');

        // Create an old game (2 months ago)
        $oldData = $this->createFinishedGameWithResult('MonthOld');
        GameResult::where('game_id', $oldData['game']->id)
            ->update(['created_at' => now()->subMonths(2)]);

        $response = $this->getJson('/api/v1/archive?period=month');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('MonthRecent', $response->json('data.0.winner_nickname'));
    }

    public function test_archive_combined_filters(): void
    {
        $this->createFinishedGameWithResult('TargetPlayer');
        $this->createFinishedGameWithResult('OtherPlayer');

        // Push OtherPlayer result to 2 weeks ago
        $otherData = $this->createFinishedGameWithResult('TargetPlayer');
        GameResult::where('game_id', $otherData['game']->id)
            ->update(['created_at' => now()->subDays(14)]);

        $response = $this->getJson('/api/v1/archive?period=week&player=Target');

        $response->assertStatus(200);
        // Only the recent TargetPlayer game should match
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_archive_round_detail_returns_single_round(): void
    {
        $data = $this->createFinishedGameWithResult('RoundDetail', 2, 3);
        $game = $data['game'];

        $response = $this->getJson("/api/v1/archive/{$game->code}/rounds/1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'round_number',
                'acronym',
                'answers' => [
                    '*' => ['player_name', 'text', 'votes_count', 'voters'],
                ],
            ]);

        $this->assertEquals(1, $response->json('round_number'));
        $this->assertEquals('ABC', $response->json('acronym'));
    }

    public function test_archive_round_detail_returns_404_for_invalid_round(): void
    {
        $data = $this->createFinishedGameWithResult('RoundFour', 2, 2);
        $game = $data['game'];

        $response = $this->getJson("/api/v1/archive/{$game->code}/rounds/99");

        $response->assertStatus(404);
    }
}
