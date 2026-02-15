<?php

use App\Application\Http\Middleware\EnsureNotBanned;
use App\Application\Http\Middleware\HandleInertiaRequests;
use App\Application\Http\Middleware\RequireAdmin;
use App\Application\Http\Middleware\RequirePlayer;
use App\Application\Http\Middleware\ResolvePlayer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->appendToGroup('api', [
            ResolvePlayer::class,
        ]);

        $middleware->alias([
            'player' => RequirePlayer::class,
            'banned' => EnsureNotBanned::class,
            'admin' => RequireAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            $status = $response->getStatusCode();

            if (in_array($status, [403, 404, 500, 503]) && ! request()->is('api/*', 'debug*')) {
                return Inertia::render('ErrorPage', [
                    'status' => $status,
                ])->toResponse(request())->setStatusCode($status);
            }

            return $response;
        });
    })->create();
