<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'owner_group_id',
        'is_primary',
        'identification_number',
        'nationality',
        'unified_number',
        'trn',
        'bank_name',
        'bank_account_no',
        'iban',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected static function booted(): void
    {
        // Only one primary owner per group — flagging a new one demotes
        // whichever member held it before, rather than leaving two.
        static::saving(function (OwnerProfile $profile): void {
            if ($profile->getAttribute('is_primary') && $profile->getAttribute('owner_group_id') !== null) {
                static::where('owner_group_id', $profile->getAttribute('owner_group_id'))
                    ->when($profile->exists, fn ($query) => $query->whereKeyNot($profile->getKey()))
                    ->update(['is_primary' => false]);
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

    /**
     * @return BelongsTo<OwnerGroup, $this>
     */
    public function ownerGroup(): BelongsTo
    {
        return $this->belongsTo(OwnerGroup::class);
    }
}
