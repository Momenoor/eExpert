<?php

namespace App\Models;

use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This installation's own license state — a single row, kept current by
 * `App\Console\Commands\VerifyLicense` (scheduled) and the installer's
 * own License step. `EnsureLicenseIsValid` reads `current()` on every
 * request, so `isValid()` stays a pure in-memory check against already-
 * fetched columns rather than anything that hits the license server
 * itself — that only ever happens in the scheduled command.
 */
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'fingerprint',
        'status',
        'plan',
        'expires_at',
        'last_checked_at',
        'last_valid_at',
    ];

    protected $casts = [
        'key' => 'encrypted',
        'expires_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'last_valid_at' => 'datetime',
    ];

    /**
     * There is only ever one license row for this installation — the
     * most recently touched one, if somehow more than one exists.
     */
    public static function current(): ?self
    {
        return static::query()->latest('updated_at')->first();
    }

    /**
     * Valid right now if the server said so within the last successful
     * check, OR — the whole point of the grace period — if it's been no
     * more than `license.grace_days` since the last time it *did* say so,
     * even if more recent check-ins have failed outright (a network
     * outage, the server being briefly down) rather than actively
     * revoked/suspended/expired the license.
     */
    public function isValid(): bool
    {
        if (in_array($this->status, ['suspended', 'revoked'], true)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->last_valid_at === null) {
            return false;
        }

        return $this->last_valid_at->addDays((int) config('license.grace_days', 7))->isFuture();
    }
}
