<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps the authenticated user's last_seen_at so the chat widget can show
 * an online/offline dot. Throttled to once every 30 seconds per user via a
 * cache lock rather than writing on every single request — this middleware
 * runs on every panel page load, and a chat feature doesn't need
 * request-level precision on "when did they last click something".
 */
class TrackUserLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(10)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
