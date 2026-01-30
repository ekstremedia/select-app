<?php

namespace App\Domain\Game\Actions;

use App\Infrastructure\Models\Game;

class GetGameByCodeAction
{
    public function execute(string $code): ?Game
    {
        return Game::where('code', strtoupper($code))->first();
    }
}
