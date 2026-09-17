<?php

namespace App\Models;

use App\Enums\PMS\AttestationFeePayer;
use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\AttestationSystem;
use App\Enums\PMS\ContractDisputeStatus;
use App\Enums\PMS\ContractPartyRole;
use App\Enums\PMS\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A tenancy agreement covering one or more units, with one or more tenants
 * (primary, co-tenants, guarantors) and its own attestation/dispute tracking.
 *
 * Status only ever moves forward through `ContractService` — never
 * hand-edited on the record — the same discipline `Quotation` follows.
 */
class Contract extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'quotation_id',
        'start_date',
        'end_date',
        'grace_period_days',
        'total_base_rent',
        'security_deposit_amount',
        'status',
        'attestation_system',
        'attestation_serial_number',
        'title_deed_number',
        'attestation_fee_payer',
        'attestation_status',
        'dispute_status',
        'tax_exemption_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'grace_period_days' => 'integer',
        'total_base_rent' => 'decimal:2',
        'security_deposit_amount' => 'decimal:2',
        'status' => ContractStatus::class,
        'attestation_system' => AttestationSystem::class,
        'attestation_fee_payer' => AttestationFeePayer::class,
        'attestation_status' => AttestationStatus::class,
        'dispute_status' => ContractDisputeStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return HasMany<ContractParty, $this>
     */
    public function contractParties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * Every party attached to this contract, with their role/parent on the
     * pivot — mirrors `Matter::parties()`.
     *
     * @return BelongsToMany<Party, $this>
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'contract_party')
            ->withPivot('id', 'role', 'parent_id');
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'contract_unit')
            ->withTimestamps();
    }

    public function primaryTenant(): ?ContractParty
    {
        return $this->contractParties->firstWhere('role', ContractPartyRole::PRIMARY_TENANT);
    }

    /**
     * @return Collection<int, ContractParty>
     */
    public function coTenants(): Collection
    {
        return $this->contractParties->where('role', ContractPartyRole::CO_TENANT);
    }

    /**
     * @return Collection<int, ContractParty>
     */
    public function guarantors(): Collection
    {
        return $this->contractParties->where('role', ContractPartyRole::GUARANTOR);
    }

    public function isEditable(): bool
    {
        return $this->getAttribute('status')->isEditable();
    }

    /**
     * What the printed contract's "Landlord" line shows — the collective
     * name of every building behind this contract's units. In the ordinary
     * case all units share one building, so this is just that building's
     * own `landlordName()`; a contract spanning more than one building shows
     * each, since there is no single estate to name instead.
     */
    public function landlordName(): string
    {
        $buildings = $this->units()->with('building')->get()->pluck('building')->filter()->unique('id');

        return $buildings->map(fn (Building $building): string => $building->landlordName())
            ->filter()
            ->unique()
            ->implode(' / ');
    }

    /**
     * The VAT fraction to apply to this contract's rent as a whole.
     *
     * A contract's units can differ in classification (an apartment plus a
     * commercial parking bay, say), and — unlike a `Quotation`, which prices
     * each unit as its own line — a contract carries one aggregate
     * `total_base_rent` with no per-unit split to apportion VAT against. This
     * weights each unit's `vatRate()` by its own posted `rental_rate` (the
     * only independent per-unit monetary figure available) rather than
     * picking one unit arbitrarily. An explicit `tax_exemption_reason`
     * (RCM/TOGC) overrides this to zero regardless of the units.
     */
    public function vatRate(): float
    {
        if ($this->getAttribute('tax_exemption_reason') !== null) {
            return 0.0;
        }

        $units = $this->units;
        $totalRentalRate = (float) $units->sum(fn (Unit $unit): float => (float) $unit->getAttribute('rental_rate'));

        if ($totalRentalRate <= 0.0) {
            return $units->isEmpty() ? 0.0 : (float) $units->avg(fn (Unit $unit): float => $unit->vatRate());
        }

        $weighted = $units->sum(
            fn (Unit $unit): float => (float) $unit->getAttribute('rental_rate') * $unit->vatRate(),
        );

        return $weighted / $totalRentalRate;
    }

    /**
     * The TRN a tax invoice should quote for the landlord side — the owner
     * group's TRN when the building's owners share one, otherwise the first
     * individual owner's.
     */
    public function landlordTrn(): ?string
    {
        $building = $this->units()->with('building.owners.ownerProfile.ownerGroup')->first()?->building;
        $owner = $building?->owners->first();

        if ($owner === null) {
            return null;
        }

        $profile = $owner->ownerProfile;

        return $profile?->getAttribute('ownerGroup')?->trn ?? $profile?->getAttribute('trn');
    }

    public function tenantTrn(): ?string
    {
        return $this->primaryTenant()?->party?->tenantProfile?->getAttribute('trn');
    }
}
