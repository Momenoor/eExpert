<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Owner-specific fields hanging off a party in the 'owner' role — mirrors
 * `EmployeeProfile`'s split of generic identity (on `Party`) from
 * role-specific detail (here).
 */
class OwnerProfile extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'owner_group_id',
        'identification_number',
        'trn',
        'bank_name',
        'bank_account_no',
        'iban',
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

    /**
     * @return BelongsTo<OwnerGroup, $this>
     */
    public function ownerGroup(): BelongsTo
    {
        return $this->belongsTo(OwnerGroup::class);
    }
}
