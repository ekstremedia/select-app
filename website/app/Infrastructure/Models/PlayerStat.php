<?php

namespace App\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'games_played',
        'games_won',
        'rounds_played',
        'rounds_won',
        'total_votes_received',
        'total_sentences_submitted',
        'best_sentence',
        'best_sentence_votes',
        'win_rate',
    ];

    protected function casts(): array
    {
        return [
            'games_played' => 'integer',
            'games_won' => 'integer',
            'rounds_played' => 'integer',
            'rounds_won' => 'integer',
            'total_votes_received' => 'integer',
            'total_sentences_submitted' => 'integer',
            'best_sentence_votes' => 'integer',
            'win_rate' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recalculateWinRate(): void
    {
        $this->win_rate = $this->games_played > 0
            ? round(($this->games_won / $this->games_played) * 100, 2)
            : 0;
        $this->save();
    }
}
