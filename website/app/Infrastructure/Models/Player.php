<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Player extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'guest_token',
        'display_name',
        'games_played',
        'games_won',
        'total_score',
    ];

    protected $casts = [
        'games_played' => 'integer',
        'games_won' => 'integer',
        'total_score' => 'integer',
    ];

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
            ->withPivot(['score', 'is_active', 'joined_at'])
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
        return $this->user_id === null;
    }
}
