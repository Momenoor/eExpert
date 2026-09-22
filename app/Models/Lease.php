<?php

namespace App\Models;

use App\Enums\PMS\AttestationFeePayer;
use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\AttestationSystem;
use App\Enums\PMS\ContractCategory;
use App\Enums\PMS\ContractType;
use App\Enums\PMS\InstallmentPaymentMethod;
use App\Enums\PMS\LeaseDisputeStatus;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\LeaseStatus;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitType;
use App\Enums\PMS\YesNo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A tenancy agreement covering one or more units, with one or more tenants
 * (primary, co-tenants, guarantors) and its own attestation/dispute tracking.
 *
 * Status only ever moves forward through `LeaseService` — never
 * hand-edited on the record — the same discipline `Quotation` follows.
 */
class Lease extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'quotation_id',
        'renewed_from_lease_id',
        'condition_template_id',
        'government_contract_number',
        'issue_date',
        'start_date',
        'end_date',
        'contract_category',
        'contract_type',
        'grace_period_days',
        'total_base_rent',
        'annual_rent',
        'multiple_rent_amount',
        'security_deposit_amount',
        'payment_method',
        'number_of_payments',
        'allow_multiple_licenses',
        'number_of_occupants',
        'status',
        'attestation_system',
        'attestation_serial_number',
        'title_deed_number',
        'attestation_fee_payer',
        'attestation_status',
        'dispute_status',
        'tax_exemption_reason',
        'poa_authority_number',
        'poa_identification_number',
        'poa_unified_number',
        'poa_name',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_category' => ContractCategory::class,
        'contract_type' => ContractType::class,
        'grace_period_days' => 'integer',
        'total_base_rent' => 'decimal:2',
        'annual_rent' => 'decimal:2',
        'multiple_rent_amount' => YesNo::class,
        'security_deposit_amount' => 'decimal:2',
        'payment_method' => InstallmentPaymentMethod::class,
        'number_of_payments' => 'integer',
        'allow_multiple_licenses' => 'boolean',
        'number_of_occupants' => 'integer',
        'status' => LeaseStatus::class,
        'attestation_system' => AttestationSystem::class,
        'attestation_fee_payer' => AttestationFeePayer::class,
        'attestation_status' => AttestationStatus::class,
        'dispute_status' => LeaseDisputeStatus::class,
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
     * @return BelongsTo<Lease, $this>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_lease_id');
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_lease_id');
    }

    /**
     * @return BelongsTo<ConditionTemplate, $this>
     */
    public function conditionTemplate(): BelongsTo
    {
        return $this->belongsTo(ConditionTemplate::class);
    }

    /**
     * @return HasMany<LeaseParty, $this>
     */
    public function leaseParties(): HasMany
    {
        return $this->hasMany(LeaseParty::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * Every party attached to this lease, with their role/parent on the
     * pivot — mirrors `Matter::parties()`.
     *
     * @return BelongsToMany<Party, $this>
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'lease_party')
            ->withPivot('id', 'role', 'parent_id');
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'lease_unit')
            ->withTimestamps();
    }

    public function primaryTenant(): ?LeaseParty
    {
        return $this->leaseParties->firstWhere('role', LeasePartyRole::PRIMARY_TENANT);
    }

    /**
     * @return Collection<int, LeaseParty>
     */
    public function coTenants(): Collection
    {
        return $this->leaseParties->where('role', LeasePartyRole::CO_TENANT);
    }

    /**
     * @return Collection<int, LeaseParty>
     */
    public function guarantors(): Collection
    {
        return $this->leaseParties->where('role', LeasePartyRole::GUARANTOR);
    }

    /**
     * The last day of a full one-year term starting on `$start` — the day
     * before the anniversary, so a lease starting 1 Jan 2026 ends 31 Dec 2026.
     */
    public static function fullYearEnd(mixed $start): Carbon
    {
        return Carbon::parse($start)->addYear()->subDay();
    }

    /**
     * Annual rent worked out from the contract period and the base rent
     * for that period: a whole number of months is scaled to twelve (one
     * full year is exactly the base rent), any other period by its days.
     */
    public static function annualRentFor(mixed $start, mixed $end, mixed $baseRent): ?float
    {
        if (blank($start) || blank($end) || blank($baseRent)) {
            return null;
        }

        $from = Carbon::parse($start)->startOfDay();
        $until = Carbon::parse($end)->startOfDay()->addDay();
        $base = (float) $baseRent;

        if ($until->lessThanOrEqualTo($from)) {
            return round($base, 2);
        }

        $months = (int) floor($from->diffInMonths($until));

        if ($months > 0 && $from->copy()->addMonths($months)->equalTo($until)) {
            return round($base * 12 / $months, 2);
        }

        return round($base * 365 / max(1, (int) round($from->diffInDays($until))), 2);
    }

    /**
     * The contract types the given units allow — the union over their
     * classifications, so a lease over a shop and a flat can be either kind.
     *
     * @param  array<int, int|string|null>  $unitIds
     * @return list<ContractType>
     */
    public static function allowedContractTypes(array $unitIds): array
    {
        $ids = array_values(array_filter($unitIds));

        if ($ids === []) {
            return [];
        }

        return Unit::whereIn('id', $ids)->get()
            ->map(fn (Unit $unit): ?PropertyClassification => $unit->getAttribute('property_classification'))
            ->filter()
            ->unique()
            ->flatMap(fn (PropertyClassification $classification): array => ContractType::forClassification($classification))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A starting choice for the Contract Type field: what the first unit's
     * own type implies, when that is one of the allowed types.
     *
     * @param  array<int, int|string|null>  $unitIds
     */
    public static function suggestContractType(array $unitIds): ?ContractType
    {
        $first = collect($unitIds)->filter()->first();
        $unitType = $first !== null ? Unit::find($first)?->getAttribute('unit_type') : null;
        $suggestion = $unitType instanceof UnitType ? ContractType::defaultForUnitType($unitType) : null;

        return $suggestion !== null && in_array($suggestion, self::allowedContractTypes($unitIds), true) ? $suggestion : null;
    }

    public function isEditable(): bool
    {
        return $this->getAttribute('status')->isEditable();
    }

    /**
     * What the printed lease's "Landlord" line shows — the collective
     * name of every property behind this lease's units. In the ordinary
     * case all units share one property, so this is just that property's
     * own `landlordName()`; a lease spanning more than one property shows
     * each, since there is no single estate to name instead.
     */
    public function landlordName(): string
    {
        $properties = $this->units()->with('property')->get()->pluck('property')->filter()->unique('id');

        return $properties->map(fn (Property $property): string => $property->landlordName())
            ->filter()
            ->unique()
            ->implode(' / ');
    }

    /**
     * The VAT fraction to apply to this lease's rent as a whole.
     *
     * A lease's units can differ in classification (an apartment plus a
     * commercial parking bay, say), and — unlike a `Quotation`, which prices
     * each unit as its own line — a lease carries one aggregate
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
     * group's TRN when the property's owners share one, otherwise the first
     * individual owner's.
     */
    public function landlordTrn(): ?string
    {
        $property = $this->units()->with('property.owners.ownerProfile.ownerGroup')->first()?->property;
        $owner = $property?->owners->first();

        if ($owner === null) {
            return null;
        }

        $profile = $owner->ownerProfile;

        return $profile?->getAttribute('ownerGroup')?->trn ?? $profile?->getAttribute('trn');
    }

    public function tenantTrn(): ?string
    {
        return $this->primaryTenant()?->party?->tenant?->getAttribute('trn');
    }

    /**
     * A human-readable "Rent Duration" (e.g. "1 Year", "18 Months") derived
     * from the lease's own dates rather than stored separately, so it can
     * never drift from `start_date`/`end_date`.
     */
    public function rentDuration(): string
    {
        $start = $this->getAttribute('start_date');
        $end = $this->getAttribute('end_date');

        if ($start === null || $end === null) {
            return '';
        }

        // Both dates are part of the term, so a lease from 1 Jan to 31 Dec
        // is a full twelve months, not eleven.
        $months = (int) $start->diffInMonths($end->copy()->addDay());

        if ($months > 0 && $months % 12 === 0) {
            $years = intdiv($months, 12);

            return trans_choice(':count Year|:count Years', $years, ['count' => $years]);
        }

        return trans_choice(':count Month|:count Months', $months, ['count' => $months]);
    }

    /**
     * How many distinct owners sit behind this lease's units — declared on
     * the printed contract but not worth storing separately from the
     * property's own `owners()` relation.
     */
    /**
     * How many distinct owners sit behind this lease's units — a shared
     * estate's own member count (its heirs/partners) when a property has
     * one, since that is the definitive owner list for it, otherwise the
     * property's individually recorded owners.
     */
    public function numberOfLessors(): int
    {
        $properties = $this->units()->with([
            'property.owners.ownerProfile.ownerGroup',
            'property.ownerGroup.ownerProfiles',
        ])->get()
            ->pluck('property')
            ->filter()
            ->unique('id');

        return $properties
            ->flatMap(function (Property $property): Collection {
                $group = $property->getAttribute('ownerGroup') ?? $property->commonOwnerGroup();

                return $group !== null
                    ? $group->ownerProfiles->pluck('party_id')
                    : $property->owners->pluck('id');
            })
            ->unique()
            ->count();
    }
}
