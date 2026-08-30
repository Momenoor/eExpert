<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSystemOffline
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If system is online, allow everything
        if (! Setting::isOffline()) {
            return $next($request);
        }
        if ($request->is('livewire/*') || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }
        // 2. Allow static assets, health checks, maintenance page & Livewire core assets
        if (
            $request->is('up') ||
            $request->is('system-down*') ||
            $request->routeIs('system-down*') ||
            $request->is('livewire/*') ||
            $request->is('css/*') ||
            $request->is('js/*') ||
            $request->is('images/*') ||
            $request->is('fonts/*')
        ) {
            return $next($request);
        }

        // 3. Allow login & logout endpoints (GET & POST)
        if (
            $request->is('admin/login*') ||
            $request->is('admin/logout*') ||
            $request->routeIs('filament.*.auth.login')

        ) {
            return $next($request);
        }

        $user = $request->user();

        // 4. If logged in, check if user is an authorized admin
        if ($user) {
            $allowAdmins = (bool) Setting::get('offline_allow_admins', true);

            if ($allowAdmins && (
                (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'super_admin', 'admin'])) ||
                ($user->is_admin ?? false)
            )) {
                return $next($request);
            }
        }

        // 5. If user is unauthenticated AND trying to access admin panel, send to login
        if (! $user && ($request->is('admin*') || $request->routeIs('filament.*'))) {
            return response()->redirectToRoute('filament.admin.auth.login');
        }

        // 6. Everyone else (unauthenticated visitors or non-admin users) gets redirected to system-down
        $message = Setting::getOfflineMessage();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => 'offline',
            ], 503);
        }

        return response()->redirectToRoute('system-down')->with('maintenance_message', $message);
    }
}
