<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every request to the installer until the application has one.
 *
 * Registered on the web group, so it runs on effectively every page — which is
 * exactly the risk: if this ever ran on an already-populated deployment with no
 * lock file, it would take the whole site offline behind a database wizard.
 * InstallationStatus::isInstalled() is what makes that safe, by treating an
 * existing user as proof of an existing install; this middleware only has to
 * get out of the way of the installer's own routes, the health check, and
 * anything serving a static asset.
 */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || app(InstallationStatus::class)->isInstalled()) {
            return $next($request);
        }

        return redirect()->route('installer.show');
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->routeIs('installer.*')
            || $request->is('up')
            || $request->is('storage/*')
            || $request->is('build/*');
    }
}
