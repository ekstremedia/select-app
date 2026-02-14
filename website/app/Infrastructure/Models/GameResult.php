<?php

namespace App\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'winner_nickname',
        'winner_user_id',
        'final_scores',
        'rounds_played',
        'player_count',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'final_scores' => 'array',
            'rounds_played' => 'integer',
            'player_count' => 'integer',
            'duration_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
