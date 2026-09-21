<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of an owner group's bank accounts. A property in the group is linked
 * to exactly one of them — the account its rent is paid into.
 */
class OwnerGroupBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_group_id',
        'bank_name',
        'account_no',
        'iban',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * @return BelongsTo<OwnerGroup, $this>
     */
    public function ownerGroup(): BelongsTo
    {
        return $this->belongsTo(OwnerGroup::class);
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function label(): string
    {
        return collect([$this->getAttribute('bank_name'), $this->getAttribute('iban') ?? $this->getAttribute('account_no')])
            ->filter()
            ->implode(' — ');
    }
}
