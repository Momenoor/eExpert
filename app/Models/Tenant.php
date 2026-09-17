<?php

namespace App\Models;

use App\Enums\PMS\TenantIdentificationType;
use App\Enums\PMS\TenantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tenant-specific fields hanging off a party in the 'tenant' role — mirrors
 * `EmployeeProfile`'s split of generic identity (on `Party`) from
 * role-specific detail (here).
 */
class Tenant extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'tenant_type',
        'identification_type',
        'identification_number',
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
}
