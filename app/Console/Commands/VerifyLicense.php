<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Services\License\LicenseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Scheduled (see `bootstrap/app.php`) to re-check this installation's
 * license periodically. A network failure or a down license server
 * leaves `last_valid_at` stale rather than marking the license invalid
 * outright — that staleness, checked against `license.grace_days` in
 * `License::isValid()`, is the whole mechanism behind the offline grace
 * period `EnsureLicenseIsValid` enforces.
 */
class VerifyLicense extends Command
{
    protected $signature = 'license:verify';

    protected $description = 'Re-check this installation\'s license against the license server.';

    public function handle(LicenseClient $client): int
    {
        $license = License::current();

        if (! $license) {
            $this->info('No license on file — nothing to verify.');

            return self::SUCCESS;
        }

        $result = $client->verify($license->key, $license->fingerprint);

        $license->last_checked_at = now();

        if ($result['valid']) {
            $license->status = 'active';
            $license->plan = $result['plan'] ?? $license->plan;
            $license->expires_at = $result['expires_at'] ? Carbon::parse($result['expires_at']) : null;
            $license->last_valid_at = now();
        } elseif (in_array($result['reason'], ['suspended', 'revoked', 'expired', 'not_found', 'not_activated'], true)) {
            // The server actively said no — unlike a network error, this
            // is authoritative and shouldn't wait out the grace period.
            $license->status = $result['reason'] === 'not_found' || $result['reason'] === 'not_activated'
                ? 'revoked'
                : $result['reason'];
        }

        $license->save();

        $this->info($result['valid'] ? 'License valid.' : "License check failed: {$result['reason']}");

        return self::SUCCESS;
    }
}
