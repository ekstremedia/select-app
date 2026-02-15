<?php

namespace App\Infrastructure\Models;

use App\Models\User;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): PlayerFactory
    {
        return PlayerFactory::new();
    }

    protected $fillable = [
        'user_id',
        'guest_token',
        'nickname',
        'is_guest',
        'is_bot',
        'games_played',
        'games_won',
        'total_score',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'is_bot' => 'boolean',
            'games_played' => 'integer',
            'games_won' => 'integer',
            'total_score' => 'integer',
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_players')
            ->withPivot(['score', 'is_active', 'is_co_host', 'joined_at'])
            ->withTimestamps();
    }

    public function hostedGames(): HasMany
    {
        return $this->hasMany(Game::class, 'host_player_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'voter_id');
    }

    public function isGuest(): bool
    {
        return $this->is_guest;
    }

    public function touchLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}
