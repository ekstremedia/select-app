<?php

namespace App\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallOfFame extends Model
{
    public $timestamps = false;

    protected $table = 'hall_of_fame';

    protected $fillable = [
        'game_id',
        'game_code',
        'round_number',
        'acronym',
        'sentence',
        'author_nickname',
        'author_user_id',
        'votes_count',
        'voter_nicknames',
        'is_round_winner',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'votes_count' => 'integer',
            'voter_nicknames' => 'array',
            'is_round_winner' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
