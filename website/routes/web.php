<?php

use App\Application\Http\Controllers\Api\V1\BroadcastAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Debug page (only in local/development)
Route::get('/debug', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return view('debug');
})->name('debug');

// Custom broadcast auth that supports guest tokens
Route::post('/api/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

// Inertia routes
Route::get('/', fn () => Inertia::render('Welcome'))->name('welcome');
Route::get('/login', fn () => Inertia::render('Login'))->name('login');
Route::get('/register', fn () => Inertia::render('Register'))->name('register');
Route::get('/forgot-password', fn () => Inertia::render('ForgotPassword'))->name('forgot-password');
Route::get('/reset-password/{token}', fn (string $token) => Inertia::render('ResetPassword', ['token' => $token]))->name('reset-password');
Route::get('/profile', fn () => Inertia::render('ProfileSettings'))->name('profile-settings');
Route::get('/profile/{nickname}', fn (string $nickname) => Inertia::render('Profile', ['nickname' => $nickname]))->name('profile');
Route::get('/games', fn () => Inertia::render('Games'))->name('games');
Route::get('/games/create', fn () => Inertia::render('CreateGame'))->name('games-create');
Route::get('/games/join', fn () => Inertia::render('JoinGame'))->name('games-join');
Route::get('/games/{code}', fn (string $code) => Inertia::render('Game', ['code' => $code]))->name('game');
Route::get('/games/{code}/spectate', fn (string $code) => Inertia::render('GameSpectate', ['code' => $code]))->name('game-spectate');
Route::get('/archive', fn () => Inertia::render('Archive'))->name('archive');
Route::get('/archive/{code}', fn (string $code) => Inertia::render('ArchiveGame', ['code' => $code]))->name('archive-game');
Route::get('/leaderboard', fn () => Inertia::render('Leaderboard'))->name('leaderboard');
Route::get('/hall-of-fame', fn () => Inertia::render('HallOfFame'))->name('hall-of-fame');
Route::get('/admin', fn () => Inertia::render('Admin'))->middleware(['auth', 'admin'])->name('admin');

Route::get('/websocket-test', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return Inertia::render('WebSocketTest');
})->name('websocket-test');
