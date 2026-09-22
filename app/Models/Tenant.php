<?php

namespace App\Models;

use App\Enums\PMS\TenantIdentificationType;
use App\Enums\PMS\TenantType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tenant-specific fields hanging off a party in the 'tenant' role — mirrors
 * `EmployeeProfile`'s split of generic identity (on `Party`) from
 * role-specific detail (here).
 */
class Tenant extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'tenant_type',
        'nationality',
        'identification_type',
        'identification_number',
        'unified_number',
        'trn',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected $casts = [
        'tenant_type' => TenantType::class,
        'identification_type' => TenantIdentificationType::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected static function booted(): void
    {
        // A tenant that has been party to a lease is part of that lease's
        // history — deleting it would leave a contract pointing at nothing.
        static::deleting(function (Tenant $tenant): void {
            if ($tenant->hasLeaseHistory()) {
                throw new RuntimeException('This tenant is linked to a lease and cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function isCompany(): bool
    {
        return $this->getAttribute('tenant_type') === TenantType::COMPANY;
    }

    public function hasLeaseHistory(): bool
    {
        $partyId = $this->getAttribute('party_id');

        return $partyId !== null && LeaseParty::where('party_id', $partyId)->exists();
    }
}
