<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after `RedirectToInstaller` has already let an installed app
 * through (see `bootstrap/app.php` — appended, not prepended, to the
 * `web` group). Locks the app down once the license has been invalid, or
 * simply un-checked, for longer than `license.grace_days` — see
 * `License::isValid()` for exactly what that means.
 */
class EnsureLicenseIsValid
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enforced() || $this->shouldBypass($request) || (License::current()?->isValid() ?? false)) {
            return $next($request);
        }

        return redirect()->route('license.show');
    }

    /**
     * Off by default in the `testing` environment — this middleware sits
     * on the whole `web` group, so without this every one of this app's
     * many existing feature tests that hits an HTTP route on an
     * "installed" test database (virtually all of them) would need to
     * seed a `License` row of its own just to keep working. Tests that
     * actually mean to exercise this middleware (see
     * `EnsureLicenseIsValidTest`) opt back in explicitly.
     */
    private function enforced(): bool
    {
        return ! app()->environment('testing') || config('license.force_enforcement_in_tests', false);
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->routeIs('license.*')
            || $request->routeIs('installer.*')
            || $request->is('up')
            || $request->is('storage/*')
            || $request->is('build/*')
            || $request->is('livewire*');
    }
}
