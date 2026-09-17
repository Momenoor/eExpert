<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A collective identity for owners administered as one estate — e.g. "Legal
 * Heirs of Mahmoud Kalbat" once a property has passed to several children
 * rather than a single owner. `Property::landlordName()` shows this group's
 * own `name` instead of listing every member individually when every owner
 * on a property shares the same group.
 *
 * `name` is the group's own field, authoritative on its own — the linked
 * Party exists only to supply the estate's own phone/email, the way
 * `OwnerProfile` hangs role-specific detail off a Party's generic identity.
 */
class OwnerGroup extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'name',
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
     * @return HasMany<OwnerProfile, $this>
     */
    public function ownerProfiles(): HasMany
    {
        return $this->hasMany(OwnerProfile::class);
    }
}
