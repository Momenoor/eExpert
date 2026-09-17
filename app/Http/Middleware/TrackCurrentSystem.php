<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps which panel (Matters Management vs Properties Management) the
 * authenticated user is currently on into ui_preferences.current_system, so
 * their last-used system is remembered across logins. Mirrors
 * TrackUserLastSeen's write-only-when-changed approach rather than writing
 * on every single request.
 */
class TrackCurrentSystem
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $system = Filament::getCurrentPanel()?->getId() === 'pms' ? 'pms' : 'mms';

        if ($user && (($user->ui_preferences['current_system'] ?? null) !== $system)) {
            $user->forceFill([
                'ui_preferences' => [...($user->ui_preferences ?? []), 'current_system' => $system],
            ])->saveQuietly();
        }

        return $next($request);
    }
}
