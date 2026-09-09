<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the installer once the application has been set up.
 *
 * Without this, `/install` stays reachable forever — a wizard that can rerun
 * migrations and hand out a fresh admin account is exactly the kind of route a
 * production system cannot leave open.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(InstallationStatus::class)->isInstalled()) {
            return redirect('/');
        }

        return $next($request);
    }
}
