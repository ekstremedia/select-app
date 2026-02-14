<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Answer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'round_id',
        'player_id',
        'text',
        'author_nickname',
        'votes_count',
    ];

    protected $casts = [
        'votes_count' => 'integer',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function recalculateVotes(): void
    {
        $this->votes_count = $this->votes()->count();
        $this->save();
    }
}
