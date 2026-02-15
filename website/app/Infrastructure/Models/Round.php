<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'game_id',
        'round_number',
        'acronym',
        'status',
        'answer_deadline',
        'vote_deadline',
        'grace_count',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'answer_deadline' => 'datetime',
        'vote_deadline' => 'datetime',
        'grace_count' => 'integer',
    ];

    public const STATUS_ANSWERING = 'answering';

    public const STATUS_VOTING = 'voting';

    public const STATUS_COMPLETED = 'completed';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function isAnswering(): bool
    {
        return $this->status === self::STATUS_ANSWERING;
    }

    public function isVoting(): bool
    {
        return $this->status === self::STATUS_VOTING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getAnswerByPlayer(string $playerId): ?Answer
    {
        return $this->answers()->where('player_id', $playerId)->first();
    }
}
