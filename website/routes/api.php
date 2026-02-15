<?php

use App\Application\Http\Controllers\Api\V1\AdminController;
use App\Application\Http\Controllers\Api\V1\ArchiveController;
use App\Application\Http\Controllers\Api\V1\AuthController;
use App\Application\Http\Controllers\Api\V1\GameController;
use App\Application\Http\Controllers\Api\V1\HallOfFameController;
use App\Application\Http\Controllers\Api\V1\LeaderboardController;
use App\Application\Http\Controllers\Api\V1\PlayerProfileController;
use App\Application\Http\Controllers\Api\V1\RoundController;
use App\Application\Http\Controllers\Api\V1\TwoFactorController;
use App\Domain\Delectus\DelectusService;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Debug routes (only in local/development)
    Route::prefix('debug')->group(function () {
        Route::get('/delectus', function (DelectusService $delectus) {
            if (! app()->environment('local', 'development')) {
                abort(404);
            }

            return response()->json($delectus->getStatus());
        });
    });

    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/guest', [AuthController::class, 'guest']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Sanctum-protected auth routes
    Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/convert', [AuthController::class, 'convert']);
    });

    // Profile routes (Sanctum-protected)
    Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
        Route::patch('/password', [AuthController::class, 'updatePassword']);
        Route::delete('/', [AuthController::class, 'deleteAccount']);
    });

    // Two-factor auth routes (Sanctum-protected)
    Route::prefix('two-factor')->middleware('auth:sanctum')->group(function () {
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/confirm', [TwoFactorController::class, 'confirm']);
        Route::delete('/disable', [TwoFactorController::class, 'disable']);
    });

    // Public read-only routes (no auth required)
    Route::get('/stats', function () {
        return response()->json([
            'games_played' => \App\Infrastructure\Models\Game::where('status', 'finished')->count(),
            'total_sentences' => \App\Infrastructure\Models\Answer::count(),
            'active_players' => \App\Infrastructure\Models\Player::where('last_active_at', '>=', now()->subDay())->count(),
        ]);
    });
    Route::get('/archive', [ArchiveController::class, 'index']);
    Route::get('/archive/{code}', [ArchiveController::class, 'show']);
    Route::get('/archive/{code}/rounds/{roundNumber}', [ArchiveController::class, 'round']);
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/hall-of-fame', [HallOfFameController::class, 'index']);
    Route::get('/hall-of-fame/random', [HallOfFameController::class, 'random']);
    Route::get('/players/{nickname}', [PlayerProfileController::class, 'show']);
    Route::get('/players/{nickname}/stats', [PlayerProfileController::class, 'stats']);
    Route::get('/players/{nickname}/sentences', [PlayerProfileController::class, 'sentences']);
    Route::get('/players/{nickname}/games', [PlayerProfileController::class, 'games']);

    // Player-required + ban-checked routes
    Route::middleware(['player', 'banned'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/profile/nickname', [AuthController::class, 'updateNickname']);

        // Game routes
        Route::prefix('games')->group(function () {
            Route::get('/', [GameController::class, 'index']);
            Route::post('/', [GameController::class, 'store']);
            Route::get('/{code}', [GameController::class, 'show']);
            Route::get('/{code}/state', [GameController::class, 'state']);
            Route::post('/{code}/join', [GameController::class, 'join']);
            Route::post('/{code}/leave', [GameController::class, 'leave']);
            Route::post('/{code}/start', [GameController::class, 'start']);
            Route::post('/{code}/chat', [GameController::class, 'chat']);
            Route::post('/{code}/co-host/{playerId}', [GameController::class, 'toggleCoHost']);
            Route::patch('/{code}/visibility', [GameController::class, 'updateVisibility']);
            Route::post('/{code}/rematch', [GameController::class, 'rematch']);
            Route::get('/{code}/rounds/current', [RoundController::class, 'current']);
        });

        // Round routes
        Route::prefix('rounds')->group(function () {
            Route::post('/{id}/answer', [RoundController::class, 'submitAnswer']);
            Route::post('/{id}/vote', [RoundController::class, 'submitVote']);
            Route::post('/{id}/voting', [RoundController::class, 'startVoting']);
            Route::post('/{id}/complete', [RoundController::class, 'complete']);
        });
    });

    // Admin routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/players', [AdminController::class, 'players']);
        Route::get('/games', [AdminController::class, 'games']);
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::post('/ban', [AdminController::class, 'ban']);
        Route::post('/unban/{playerId}', [AdminController::class, 'unban']);
    });
});
