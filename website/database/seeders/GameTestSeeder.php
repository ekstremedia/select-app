<?php

namespace Database\Seeders;

use App\Domain\Game\Actions\CreateGameAction;
use App\Domain\Game\Actions\JoinGameAction;
use App\Domain\Game\Actions\StartGameAction;
use App\Domain\Player\Actions\CreateGuestPlayerAction;
use App\Domain\Round\Actions\SubmitAnswerAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds test games for development and debugging.
 *
 * Usage:
 *   php artisan db:seed --class=GameTestSeeder
 *
 * This creates:
 *   - 3 guest players
 *   - A game in lobby state
 *   - A game in playing state (round 1, answering phase)
 *   - A game in voting state
 */
class GameTestSeeder extends Seeder
{
    public function __construct(
        private CreateGuestPlayerAction $createGuest,
        private CreateGameAction $createGame,
        private JoinGameAction $joinGame,
        private StartGameAction $startGame,
        private SubmitAnswerAction $submitAnswer,
    ) {}

    public function run(): void
    {
        $this->command->info('Creating test players...');

        // Create players
        $players = [];
        foreach (['Alice', 'Bob', 'Charlie'] as $name) {
            $player = $this->createGuest->execute($name . '_' . substr(uniqid(), -4));
            $players[] = $player;
            $this->command->info("  Created player: {$player->display_name}");
        }

        // Game 1: Lobby state
        $this->command->info('Creating game in LOBBY state...');
        $game1 = $this->createGame->execute($players[0], [
            'rounds' => 3,
            'answer_time' => 60,
            'vote_time' => 30,
        ]);
        $this->joinGame->execute($game1, $players[1]);
        $this->command->info("  Game code: {$game1->code} (lobby, 2 players)");

        // Game 2: Playing state (answering phase)
        $this->command->info('Creating game in PLAYING state...');
        $game2 = $this->createGame->execute($players[0], [
            'rounds' => 3,
            'answer_time' => 120, // Longer for testing
            'vote_time' => 60,
        ]);
        $this->joinGame->execute($game2, $players[1]);
        $this->joinGame->execute($game2, $players[2]);
        $this->startGame->execute($game2, $players[0]);
        $this->command->info("  Game code: {$game2->code} (playing, round 1, 3 players)");

        // Game 3: Playing state with some answers
        $this->command->info('Creating game with ANSWERS submitted...');
        $game3 = $this->createGame->execute($players[0], [
            'rounds' => 3,
            'answer_time' => 120,
            'vote_time' => 60,
        ]);
        $this->joinGame->execute($game3, $players[1]);
        $this->joinGame->execute($game3, $players[2]);
        $this->startGame->execute($game3, $players[0]);

        // Submit some answers
        $round = $game3->currentRound();
        if ($round) {
            $acronym = $round->acronym;
            $this->submitAnswer->execute($round, $players[0], $this->generateAnswer($acronym));
            $this->submitAnswer->execute($round, $players[1], $this->generateAnswer($acronym));
            $this->command->info("  Game code: {$game3->code} (playing, 2/3 answers submitted)");
            $this->command->info("  Acronym: {$acronym}");
        }

        $this->command->newLine();
        $this->command->info('Test games created successfully!');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->table(
            ['Code', 'Status', 'Players', 'Notes'],
            [
                [$game1->code, 'lobby', '2', 'Waiting to start'],
                [$game2->code, 'playing', '3', 'Round 1, no answers'],
                [$game3->code, 'playing', '3', 'Round 1, 2 answers'],
            ]
        );
    }

    /**
     * Generate a fake answer for an acronym.
     */
    private function generateAnswer(string $acronym): string
    {
        $words = [
            'A' => ['Always', 'Amazing', 'Awesome'],
            'B' => ['Big', 'Beautiful', 'Brilliant'],
            'C' => ['Cool', 'Creative', 'Clever'],
            'D' => ['Dancing', 'Daring', 'Dynamic'],
            'E' => ['Exciting', 'Elegant', 'Epic'],
            'F' => ['Fantastic', 'Funny', 'Fast'],
            'G' => ['Great', 'Groovy', 'Glorious'],
            'H' => ['Happy', 'Huge', 'Helpful'],
            'I' => ['Incredible', 'Interesting', 'Inspiring'],
            'J' => ['Jolly', 'Jumping', 'Joyful'],
            'K' => ['Kind', 'Keen', 'Kicking'],
            'L' => ['Lovely', 'Lucky', 'Loud'],
            'M' => ['Magical', 'Mighty', 'Marvelous'],
            'N' => ['Nice', 'Noble', 'Nifty'],
            'O' => ['Outstanding', 'Original', 'Optimal'],
            'P' => ['Perfect', 'Powerful', 'Pleasant'],
            'Q' => ['Quick', 'Quiet', 'Quality'],
            'R' => ['Radical', 'Remarkable', 'Robust'],
            'S' => ['Super', 'Stunning', 'Splendid'],
            'T' => ['Terrific', 'Tremendous', 'Thrilling'],
            'U' => ['Ultimate', 'Unique', 'Unstoppable'],
            'V' => ['Vibrant', 'Victorious', 'Valuable'],
            'W' => ['Wonderful', 'Wild', 'Wise'],
            'X' => ['X-cellent', 'X-treme', 'Xenial'],
            'Y' => ['Youthful', 'Yummy', 'Yielding'],
            'Z' => ['Zesty', 'Zealous', 'Zippy'],
        ];

        $answer = [];
        foreach (str_split($acronym) as $letter) {
            $options = $words[strtoupper($letter)] ?? ['Unknown'];
            $answer[] = $options[array_rand($options)];
        }

        return implode(' ', $answer);
    }
}
