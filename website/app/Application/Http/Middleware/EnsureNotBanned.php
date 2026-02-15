<?php

namespace App\Application\Http\Middleware;

use App\Infrastructure\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('sanctum')->user();

        // Check if authenticated user is banned
        if ($user && $user->isBanned()) {
            return response()->json([
                'error' => 'Your account has been banned.',
                'reason' => $user->ban_reason,
            ], 403);
        }

        // Check IP ban for guests
        $player = $request->attributes->get('player');
        if ($player && $player->isGuest()) {
            try {
                $ipBanned = BannedIp::where('ip_address', $request->ip())->exists();
                if ($ipBanned) {
                    return response()->json([
                        'error' => 'Your IP address has been banned.',
                    ], 403);
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // Table may not exist yet — skip IP ban check
            }
        }

        return $next($request);
    }
}
