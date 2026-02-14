<?php

namespace Database\Factories;

use App\Infrastructure\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => fake()->unique()->regexify('[A-Za-z0-9_]{3,15}'),
            'guest_token' => Str::random(64),
            'is_guest' => true,
            'games_played' => 0,
            'games_won' => 0,
            'total_score' => 0,
            'last_active_at' => now(),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_guest' => true,
            'guest_token' => Str::random(64),
            'user_id' => null,
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_guest' => false,
            'guest_token' => null,
            'user_id' => \App\Models\User::factory(),
        ]);
    }
}
