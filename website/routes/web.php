<?php

use App\Application\Http\Controllers\Api\V1\BroadcastAuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Debug page (only in local/development)
Route::get('/debug', function () {
    if (! app()->environment('local', 'development')) {
        abort(404);
    }

    return view('debug');
})->name('debug');

// Custom broadcast auth that supports guest tokens
Route::post('/api/broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

// SPA catch-all — serves the Vue app for all frontend routes
Route::get('/{any?}', function () {
    $gullkorn = null;
    try {
        if (Schema::hasTable('gullkorn_clean')) {
            $gullkorn = DB::table('gullkorn_clean')
                ->where('stemmer', '>', 4)
                ->inRandomOrder()
                ->first();
        }
    } catch (\Throwable) {
        // Table may not exist yet
    }

    return view('welcome', [
        'gullkorn' => $gullkorn?->setning,
    ]);
})->where('any', '^(?!api|debug|sanctum).*$');
