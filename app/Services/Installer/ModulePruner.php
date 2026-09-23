<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\File;

/**
 * Physically relocates a disabled module's source files to cold storage and
 * back again.
 *
 * The installer calls {@see prune()} once, automatically, for whichever
 * module the client didn't license — this is deliberately a one-way action
 * per deployment, not just a config flag, so the other module's source code
 * genuinely isn't present on disk afterward. {@see restore()} is the inverse,
 * used by the `module:enable` Artisan command when a client's license is
 * upgraded later.
 *
 * The manifest of paths per module lives in `config/module_manifest.php`.
 */
class ModulePruner
{
    /**
     * Move every manifest path for `$module` from its live location into
     * `storage/app/disabled-modules/{module}/{same-relative-path}`.
     *
     * Skips any manifest path that doesn't currently exist — already pruned,
     * or never existed in this deployment — rather than failing.
     */
    public function prune(string $module): void
    {
        foreach ($this->manifestPaths($module) as $relativePath) {
            $live = base_path($relativePath);

            if (! File::exists($live)) {
                continue;
            }

            $stashed = $this->stashPath($module, $relativePath);

            File::ensureDirectoryExists(dirname($stashed));

            if (File::isDirectory($live)) {
                File::moveDirectory($live, $stashed, overwrite: true);
            } else {
                File::move($live, $stashed);
            }
        }
    }

    /**
     * Move everything previously stashed for `$module` back to its original
     * live path. No-ops entirely if there is no stash for that module.
     */
    public function restore(string $module): void
    {
        if (! $this->isPruned($module)) {
            return;
        }

        foreach ($this->manifestPaths($module) as $relativePath) {
            $stashed = $this->stashPath($module, $relativePath);

            if (! File::exists($stashed)) {
                continue;
            }

            $live = base_path($relativePath);

            File::ensureDirectoryExists(dirname($live));

            if (File::isDirectory($stashed)) {
                File::moveDirectory($stashed, $live, overwrite: true);
            } else {
                File::move($stashed, $live);
            }
        }

        $this->cleanupEmptyStash($module);
    }

    /**
     * Whether `$module` currently has files sitting in cold storage.
     */
    public function isPruned(string $module): bool
    {
        $stashRoot = $this->stashRoot($module);

        if (! File::isDirectory($stashRoot)) {
            return false;
        }

        // Only actual files count — a restore can leave now-empty parent
        // directories behind in the stash (see cleanupEmptyStash()), and
        // those shouldn't make an already-restored module look pruned.
        return File::allFiles($stashRoot) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function manifestPaths(string $module): array
    {
        return config("module_manifest.{$module}.paths", []);
    }

    private function stashRoot(string $module): string
    {
        return storage_path("app/disabled-modules/{$module}");
    }

    private function stashPath(string $module, string $relativePath): string
    {
        return $this->stashRoot($module).DIRECTORY_SEPARATOR.$relativePath;
    }

    /**
     * Remove the module's stash root after a successful restore. Individual
     * file moves can leave now-empty parent directories behind, so this
     * clears the whole tree once no real files remain in it.
     */
    private function cleanupEmptyStash(string $module): void
    {
        $stashRoot = $this->stashRoot($module);

        if (File::isDirectory($stashRoot) && File::allFiles($stashRoot) === []) {
            File::deleteDirectory($stashRoot);
        }
    }
}
