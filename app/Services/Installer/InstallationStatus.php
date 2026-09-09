<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Whether this deployment has been through the installer.
 *
 * The authoritative signal is a lock file (`storage/installed`), because it is
 * the cheapest possible check and it has to run on every request that is not
 * itself part of the installer. But a lock file is something a NEW deployment
 * of this codebase will not have, and an EXISTING deployment — this one
 * included — will not have either, since it was never created by an installer
 * in the first place. Treating "no lock file" as "not installed" on an
 * existing, populated system would redirect every real user to a database-setup
 * wizard, which is worse than the problem the installer solves.
 *
 * So the fallback path asks the database directly: if `users` already has a
 * row, this is an existing, working system, and the lock file is written on the
 * spot so the next request skips the query. Only a database that is reachable
 * but genuinely empty of users — or unreachable at all — is treated as not
 * installed.
 */
class InstallationStatus
{
    public static function lockFilePath(): string
    {
        return config('installer.lock_file', storage_path('installed'));
    }

    public function isInstalled(): bool
    {
        if (File::exists(self::lockFilePath())) {
            return true;
        }

        if ($this->hasExistingUsers()) {
            // Backfill the lock file so this fallback query only ever runs once
            // per deployment, not once per request.
            $this->markInstalled();

            return true;
        }

        return false;
    }

    public function markInstalled(): void
    {
        File::ensureDirectoryExists(dirname(self::lockFilePath()));
        File::put(self::lockFilePath(), now()->toDateTimeString()."\n");
    }

    /**
     * True only if the database is reachable, migrated, and already has at
     * least one user — never throws, since "not installed" is the safe default
     * for anything that looks even slightly like a fresh environment.
     */
    private function hasExistingUsers(): bool
    {
        try {
            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
