<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class GullkornClean extends Model
{
    protected $table = 'gullkorn_clean';

    public $timestamps = false;

    protected $fillable = [
        'nick',
        'setning',
        'stemmer',
        'tid',
        'hvemstemte',
    ];
}
