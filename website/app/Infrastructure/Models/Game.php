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
        'is_public',
        'password',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected $casts = [
        'settings' => 'array',
        'current_round' => 'integer',
        'total_rounds' => 'integer',
        'is_public' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    protected $hidden = [
        'password',
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
            ->withPivot(['score', 'is_active', 'is_co_host', 'joined_at'])
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

    public function isHostOrCoHost(Player $player): bool
    {
        if ($this->host_player_id === $player->id) {
            return true;
        }

        return $this->gamePlayers()
            ->where('player_id', $player->id)
            ->where('is_co_host', true)
            ->exists();
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

    public function gameResult()
    {
        return $this->hasOne(\App\Infrastructure\Models\GameResult::class);
    }

    public function getDefaultSettings(): array
    {
        return [
            'min_players' => 2,
            'max_players' => 10,
            'rounds' => 8,
            'answer_time' => 120,
            'vote_time' => 60,
            'time_between_rounds' => 15,
            'acronym_length_min' => 5,
            'acronym_length_max' => 5,
            'excluded_letters' => '',
        ];
    }

    public function scopePublicLobby($query)
    {
        return $query->where('is_public', true)
            ->where('status', self::STATUS_LOBBY);
    }

    public function scopePublicJoinable($query)
    {
        return $query->where('is_public', true)
            ->whereIn('status', [self::STATUS_LOBBY, self::STATUS_PLAYING, self::STATUS_VOTING]);
    }

    public function scopeFinished($query)
    {
        return $query->where('status', self::STATUS_FINISHED);
    }
}
