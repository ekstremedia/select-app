<?php

use App\Application\Http\Controllers\Api\V1\AuthController;
use App\Application\Http\Controllers\Api\V1\GameController;
use App\Application\Http\Controllers\Api\V1\RoundController;
use App\Domain\Delectus\DelectusService;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Debug routes (only in local/development)
    Route::prefix('debug')->group(function () {
        Route::get('/delectus', function (DelectusService $delectus) {
            if (!app()->environment('local', 'development')) {
                abort(404);
            }
            return response()->json($delectus->getStatus());
        });
    });

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/guest', [AuthController::class, 'guest']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/convert', [AuthController::class, 'convert']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Game routes
    Route::prefix('games')->group(function () {
        Route::post('/', [GameController::class, 'store']);
        Route::get('/{code}', [GameController::class, 'show']);
        Route::post('/{code}/join', [GameController::class, 'join']);
        Route::post('/{code}/leave', [GameController::class, 'leave']);
        Route::post('/{code}/start', [GameController::class, 'start']);
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
