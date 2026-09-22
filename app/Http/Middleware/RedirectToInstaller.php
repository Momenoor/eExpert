<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        $installed = app(InstallationStatus::class)->isInstalled();

        // Checked directly against the database rather than only inferred
        // from `$installed` — a stale `storage/installed` lock file left
        // over from an earlier attempt (or one auto-written by
        // InstallationStatus the moment it ever sees a `users` row) can
        // say "installed" even against a database an operator has since
        // reset to try again. Session, cache, and queue all default to
        // the `database` driver in a freshly-copied `.env`, and every one
        // of them fails the exact same way against a table that doesn't
        // exist until the installer's own Install step runs `migrate` —
        // Livewire itself already touches the cache (its checksum-failure
        // tracking) on every single request, not just the session.
        if (! $installed || $this->tableMissing('session.driver', 'session.table', 'sessions')) {
            // StartSession resolves the driver lazily later in this same
            // pipeline, so overriding it here — before that happens — is
            // enough to avoid ever hitting the database for it.
            config([
                'session.driver' => 'file',
                'session.files' => storage_path('framework/sessions'),
            ]);
        }

        if (! $installed || $this->tableMissing('cache.default', 'cache.stores.database.table', 'cache')) {
            config(['cache.default' => 'file']);
        }

        if (! $installed || $this->tableMissing('queue.default', 'queue.connections.database.table', 'jobs')) {
            config(['queue.default' => 'sync']);
        }

        if (! $installed) {
            // The installer runs before the operator has picked anything —
            // including a language — and `APP_LOCALE` in a freshly-copied
            // `.env` may already be set to this deployment's own default
            // (Arabic, per .env.example). English only, the whole way
            // through: this only ever applies while not yet installed, so
            // it never touches the app's real locale once it's up.
            app()->setLocale('en');
        }

        if ($this->shouldBypass($request) || $installed) {
            return $next($request);
        }

        return redirect()->route('installer.show');
    }

    /**
     * Only meaningful when the given config actually resolves to the
     * `database` driver — every other driver never touches any table at
     * all, and the check is skipped entirely for it.
     */
    private function tableMissing(string $driverKey, string $tableKey, string $defaultTable): bool
    {
        if (config($driverKey) !== 'database') {
            return false;
        }

        try {
            return ! Schema::hasTable(config($tableKey, $defaultTable));
        } catch (Throwable) {
            // Can't even reach the database to ask — treat that exactly
            // like the table not existing, since either way the real
            // `database` driver is about to fail the same request.
            return true;
        }
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->routeIs('installer.*')
            || $request->is('up')
            || $request->is('storage/*')
            || $request->is('build/*')
            // Livewire's own JS/CSS/update/upload endpoints — their URI
            // prefix is a hash unique to this install (`livewire-<hash>/`),
            // never a fixed segment, and their route names aren't all
            // under one consistent prefix either. Without this, every
            // wire:click on the installer's own page sends its AJAX
            // update request straight into this same redirect, which the
            // browser follows and gets back the installer's full HTML
            // page instead of Livewire's expected JSON — the wizard just
            // appears to reload itself instead of advancing a step.
            || $request->is('livewire*');
    }
}
