<?php

use App\Application\Http\Controllers\Api\V1\BroadcastAuthController;
use App\Infrastructure\Models\Game;
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

// Inertia routes (Norwegian)
Route::get('/', fn () => Inertia::render('Welcome'))->name('welcome');
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
Route::get('/profil', fn () => Inertia::render('ProfileSettings'))->middleware('auth')->name('profile-settings');
Route::get('/profil/{nickname}', fn (string $nickname) => Inertia::render('Profile', ['nickname' => $nickname]))->name('profile');
Route::get('/spill', fn () => Inertia::render('Games'))->name('games');
Route::get('/spill/opprett', fn () => Inertia::render('CreateGame'))->name('games-create');
Route::get('/spill/bli-med', fn () => Inertia::render('JoinGame'))->name('games-join');
Route::get('/spill/{code}', fn (string $code) => Inertia::render('Game', ['code' => $code]))->name('game');
Route::get('/spill/{code}/se', fn (string $code) => Inertia::render('GameSpectate', ['code' => $code]))->name('game-spectate');
Route::get('/arkiv', fn () => Inertia::render('Archive'))->name('archive');
Route::get('/arkiv/{code}', fn (string $code) => Inertia::render('ArchiveGame', ['code' => $code]))->name('archive-game');
Route::get('/toppliste', fn () => Inertia::render('Leaderboard'))->name('leaderboard');
Route::get('/hall-of-fame', fn () => Inertia::render('HallOfFame'))->name('hall-of-fame');
Route::get('/admin', fn () => Inertia::render('Admin'))->middleware(['auth', 'admin'])->name('admin');

Route::get('/websocket-test', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return Inertia::render('WebSocketTest');
})->name('websocket-test');
