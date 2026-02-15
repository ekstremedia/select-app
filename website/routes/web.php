<?php

use App\Application\Http\Controllers\Api\V1\BroadcastAuthController;
use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Helper: fetch game preview for invite redirects
if (! function_exists('getGamePreviewFromRedirect')) {
    function getGamePreviewFromRedirect(?string $redirect): ?array
    {
        if (! $redirect || ! preg_match('#^/spill/([A-Z0-9]{4,6})$#i', $redirect, $matches)) {
            return null;
        }

        $game = Game::where('code', strtoupper($matches[1]))
            ->with('host')
            ->first();

        if (! $game || $game->isFinished()) {
            return null;
        }

        $players = $game->activePlayers()->get();

        return [
            'code' => $game->code,
            'host_nickname' => $game->host?->nickname,
            'player_count' => $players->count(),
            'max_players' => $game->settings['max_players'] ?? 10,
            'players' => $players->pluck('nickname')->values()->toArray(),
        ];
    }
}

// Debug page (only in local/development)
Route::get('/debug', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return view('debug');
})->name('debug');

// Custom broadcast auth that supports guest tokens
Route::post('/api/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

// Helper: build game meta tags for OG sharing
if (! function_exists('getGameMeta')) {
    function getGameMeta(string $code, string $variant = 'invite'): array
    {
        $game = Game::where('code', strtoupper($code))
            ->with('host')
            ->first();

        if (! $game) {
            return ['title' => 'Select — Spill ikke funnet'];
        }

        $playerCount = $game->activePlayers()->count();
        $maxPlayers = $game->settings['max_players'] ?? 8;
        $hostName = $game->host?->nickname ?? 'Ukjent';

        if ($game->isFinished()) {
            return [
                'title' => "Spill #{$game->code} — Select",
                'description' => "Ferdig spill fra {$hostName}. Se resultater i arkivet!",
            ];
        }

        if ($variant === 'spectate') {
            return [
                'title' => "Se spill #{$game->code} — Select",
                'description' => "Se på et spill med {$playerCount}/{$maxPlayers} spillere. Vert: {$hostName}",
            ];
        }

        return [
            'title' => "Bli med i spill #{$game->code} — Select",
            'description' => "{$playerCount}/{$maxPlayers} spillere — Vert: {$hostName}. Bli med og lag de morsomste setningene!",
        ];
    }
}

// Inertia routes (Norwegian)
Route::get('/', fn () => Inertia::render('Welcome', [
    'meta' => [
        'title' => 'Select — Akronym-spillet fra #select på EFnet',
        'description' => 'Det klassiske akronym-spillet der kreativitet og humor vinner. Lag setninger av tilfeldige bokstaver og stem på de beste!',
    ],
]))->name('welcome');
Route::get('/logg-inn', function (Request $request) {
    return Inertia::render('Login', [
        'gamePreview' => getGamePreviewFromRedirect($request->query('redirect')),
    ]);
})->name('login');
Route::get('/registrer', function (Request $request) {
    return Inertia::render('Register', [
        'gamePreview' => getGamePreviewFromRedirect($request->query('redirect')),
    ]);
})->name('register');
Route::get('/glemt-passord', fn () => Inertia::render('ForgotPassword'))->name('forgot-password');
Route::get('/nytt-passord/{token}', fn (string $token) => Inertia::render('ResetPassword', ['token' => $token]))->name('reset-password');
Route::get('/profil', fn () => Inertia::render('ProfileSettings'))->name('profile-settings');
Route::get('/profil/{nickname}', function (string $nickname) {
    $player = Player::where('nickname', $nickname)->first();

    return Inertia::render('Profile', [
        'nickname' => $nickname,
        'meta' => [
            'title' => $player ? "{$player->nickname} — Select" : 'Spiller — Select',
            'description' => $player ? "Se profilen til {$player->nickname} på Select" : 'Spillerprofil på Select',
        ],
    ]);
})->name('profile');
Route::get('/spill', fn () => Inertia::render('Games', [
    'meta' => [
        'title' => 'Spill — Select',
        'description' => 'Finn åpne spill eller opprett ditt eget. Bli med og lag de morsomste setningene!',
    ],
]))->name('games');
Route::get('/spill/opprett', fn () => Inertia::render('CreateGame'))->name('games-create');
Route::get('/spill/bli-med', fn () => Inertia::render('JoinGame'))->name('games-join');
Route::get('/spill/{code}', fn (string $code) => Inertia::render('Game', [
    'code' => $code,
    'meta' => getGameMeta($code, 'invite'),
]))->name('game');
Route::get('/spill/{code}/se', fn (string $code) => Inertia::render('GameSpectate', [
    'code' => $code,
    'meta' => getGameMeta($code, 'spectate'),
]))->name('game-spectate');
Route::get('/arkiv', fn () => Inertia::render('Archive', [
    'meta' => [
        'title' => 'Spillarkiv — Select',
        'description' => 'Se tidligere spill og de beste setningene fra Select',
    ],
]))->name('archive');
Route::get('/arkiv/{code}', fn (string $code) => Inertia::render('ArchiveGame', [
    'code' => $code,
    'meta' => getGameMeta($code, 'archive'),
]))->name('archive-game');
Route::get('/toppliste', fn () => Inertia::render('Leaderboard', [
    'meta' => [
        'title' => 'Toppliste — Select',
        'description' => 'Se hvem som er best i Select! Topplisten over de beste akronym-spillerne.',
    ],
]))->name('leaderboard');
Route::get('/hall-of-fame', fn () => Inertia::render('HallOfFame', [
    'meta' => [
        'title' => 'Hall of Fame — Select',
        'description' => 'De beste setningene noensinne fra Select og det originale IRC-spillet på #select (EFnet)',
    ],
]))->name('hall-of-fame');
Route::get('/admin', fn () => Inertia::render('Admin'))->middleware(['auth', 'admin'])->name('admin');

Route::get('/websocket-test', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return Inertia::render('WebSocketTest');
})->name('websocket-test');
