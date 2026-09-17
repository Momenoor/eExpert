<?php

namespace App\Models;

use App\Enums\PMS\LeasePartyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One party's stake in a lease — primary tenant, co-tenant, or guarantor.
 * Mirrors `MatterParty`: a pivot-with-identity row rather than a plain
 * pivot, so a guarantor's own row can point back at the tenant it guarantees
 * via `parent_id`.
 */
class LeaseParty extends Model
{
    use LogsActivity;

    protected $table = 'lease_party';

    protected $fillable = [
        'lease_id',
        'party_id',
        'role',
        'parent_id',
    ];

    protected $casts = [
        'role' => LeasePartyRole::class,
    ];

    public $timestamps = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * The tenant row this one refers back to (a guarantor's or co-tenant's
     * link to the primary tenant they're attached to).
     *
     * @return BelongsTo<LeaseParty, $this>
     */
    public function parentLeaseParty(): BelongsTo
    {
        return $this->belongsTo(LeaseParty::class, 'parent_id', 'id');
    }

    /**
     * Guarantors attached to this row.
     *
     * @return HasMany<LeaseParty, $this>
     */
    public function guarantors(): HasMany
    {
        return $this->hasMany(LeaseParty::class, 'parent_id', 'id')
            ->where('role', LeasePartyRole::GUARANTOR);
    }
}
