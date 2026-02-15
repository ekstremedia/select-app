<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'game_id',
        'player_id',
        'score',
        'is_active',
        'is_co_host',
        'kicked_by',
        'joined_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'is_active' => 'boolean',
        'is_co_host' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function isKicked(): bool
    {
        return $this->kicked_by !== null;
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
