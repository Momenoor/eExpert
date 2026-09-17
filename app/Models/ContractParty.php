<?php

namespace App\Models;

use App\Enums\PMS\ContractPartyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One party's stake in a contract — primary tenant, co-tenant, or guarantor.
 * Mirrors `MatterParty`: a pivot-with-identity row rather than a plain
 * pivot, so a guarantor's own row can point back at the tenant it guarantees
 * via `parent_id`.
 */
class ContractParty extends Model
{
    use LogsActivity;

    protected $table = 'contract_party';

    protected $fillable = [
        'contract_id',
        'party_id',
        'role',
        'parent_id',
    ];

    protected $casts = [
        'role' => ContractPartyRole::class,
    ];

    public $timestamps = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
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
     * @return BelongsTo<ContractParty, $this>
     */
    public function parentContractParty(): BelongsTo
    {
        return $this->belongsTo(ContractParty::class, 'parent_id', 'id');
    }

    /**
     * Guarantors attached to this row.
     *
     * @return HasMany<ContractParty, $this>
     */
    public function guarantors(): HasMany
    {
        return $this->hasMany(ContractParty::class, 'parent_id', 'id')
            ->where('role', ContractPartyRole::GUARANTOR);
    }
}
