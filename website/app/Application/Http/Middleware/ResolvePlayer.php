<?php

namespace App\Application\Http\Middleware;

use App\Infrastructure\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolvePlayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $player = null;

        // Try guest token first
        $guestToken = $request->header('X-Guest-Token');
        if ($guestToken) {
            $player = Player::where('guest_token', $guestToken)->first();
        }

        // Try authenticated user (resolve Sanctum guard explicitly)
        if (! $player) {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                $player = Player::where('user_id', $user->id)->first();
            }
        }

        if ($player) {
            $request->attributes->set('player', $player);
            $player->touchLastActive();
        }

        return $next($request);
    }
}
