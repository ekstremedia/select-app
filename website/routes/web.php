<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Debug page (only in local/development)
Route::get('/debug', function () {
    if (!app()->environment('local', 'development')) {
        abort(404);
    }
    return view('debug');
})->name('debug');
