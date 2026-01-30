<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'host_player_id',
        'status',
        'settings',
        'current_round',
        'total_rounds',
    ];

    protected $casts = [
        'settings' => 'array',
        'current_round' => 'integer',
        'total_rounds' => 'integer',
    ];

    public const STATUS_LOBBY = 'lobby';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_VOTING = 'voting';
    public const STATUS_FINISHED = 'finished';

    public function host(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'host_player_id');
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'game_players')
            ->withPivot(['score', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function activePlayers()
    {
        return $this->players()->wherePivot('is_active', true);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function currentRoundModel(): ?Round
    {
        return $this->rounds()->where('round_number', $this->current_round)->first();
    }

    /**
     * Get the current active round (answering or voting).
     * Used by Delectus to find rounds needing attention.
     */
    public function currentRound(): ?Round
    {
        return $this->rounds()
            ->whereIn('status', [Round::STATUS_ANSWERING, Round::STATUS_VOTING])
            ->first();
    }

    public function isInLobby(): bool
    {
        return $this->status === self::STATUS_LOBBY;
    }

    public function isPlaying(): bool
    {
        return $this->status === self::STATUS_PLAYING;
    }

    public function isVoting(): bool
    {
        return $this->status === self::STATUS_VOTING;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function getDefaultSettings(): array
    {
        return [
            'min_players' => 2,
            'max_players' => 8,
            'rounds' => 5,
            'answer_time' => 60,
            'vote_time' => 30,
            'acronym_length_min' => 3,
            'acronym_length_max' => 6,
        ];
    }
}
