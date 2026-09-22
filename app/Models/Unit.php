<?php

namespace App\Models;

use App\Enums\PMS\LeaseStatus;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitStatus;
use App\Enums\PMS\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A single rentable space within a property.
 *
 * `vatRate()`/`isTaxable()` are the ONLY place VAT applicability is derived
 * from a unit's classification — quotation and installment generation both
 * call through here rather than re-deriving the same 0%/5% rule.
 *
 * @method static count()
 * @method static where(string $string, UnitStatus $VACANT)
 */
class Unit extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'unit_number',
        'floor',
        'rental_rate',
        'area_sqm',
        'number_of_rooms',
        'premise_number',
        'property_classification',
        'unit_type',
        'status',
    ];

    protected $casts = [
        'rental_rate' => 'decimal:2',
        'area_sqm' => 'decimal:2',
        'number_of_rooms' => 'integer',
        'property_classification' => PropertyClassification::class,
        'unit_type' => UnitType::class,
        'status' => UnitStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected static function booted(): void
    {
        static::created(fn (Unit $unit) => self::syncPropertyTotalUnits($unit->getAttribute('property_id')));
        static::deleted(fn (Unit $unit) => self::syncPropertyTotalUnits($unit->getAttribute('property_id')));
        static::restored(fn (Unit $unit) => self::syncPropertyTotalUnits($unit->getAttribute('property_id')));

        static::updated(function (Unit $unit): void {
            // A unit moved between properties updates both sides' counts.
            if ($unit->wasChanged('property_id')) {
                self::syncPropertyTotalUnits($unit->getAttribute('property_id'));
                self::syncPropertyTotalUnits($unit->getOriginal('property_id'));
            }

            if ($unit->wasChanged(['unit_type', 'property_classification'])) {
                $unit->clearContractTypeOnDraftLeasesIfNoLongerAllowed();
            }
        });

        // A unit already tied to a lease is part of that lease's history —
        // deleting it would leave a contract pointing at nothing.
        static::deleting(function (Unit $unit): void {
            if ($unit->hasLeaseHistory()) {
                throw new RuntimeException('This unit is linked to a lease and cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsToMany<Lease, $this>
     */
    public function leases(): BelongsToMany
    {
        return $this->belongsToMany(Lease::class, 'lease_unit')
            ->withTimestamps();
    }

    public function hasLeaseHistory(): bool
    {
        return $this->leases()->exists();
    }

    /**
     * A unit's type/classification only ever corrects a lease's own
     * `contract_type` while that lease is still `DRAFT` — an active or
     * terminated contract's recorded type is history and stays exactly as
     * attested, whatever the unit is edited to afterward.
     */
    private function clearContractTypeOnDraftLeasesIfNoLongerAllowed(): void
    {
        $this->leases()
            ->where('status', LeaseStatus::DRAFT->value)
            ->get()
            ->each(function (Lease $lease): void {
                $allowed = Lease::allowedContractTypes($lease->units()->pluck('units.id')->all());
                $current = $lease->getAttribute('contract_type');

                if ($current !== null && ! in_array($current, $allowed, true)) {
                    $lease->forceFill(['contract_type' => null])->save();
                }
            });
    }

    /**
     * Recomputes a property's `total_units` from its actual (non-trashed)
     * units, rather than incrementing/decrementing a counter that could
     * drift out of sync with reality.
     */
    private static function syncPropertyTotalUnits(mixed $propertyId): void
    {
        if ($propertyId === null) {
            return;
        }

        Property::whereKey($propertyId)->update([
            'total_units' => self::where('property_id', $propertyId)->count(),
        ]);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isVacant(): bool
    {
        return $this->getAttribute('status') === UnitStatus::VACANT;
    }

    /**
     * The VAT fraction (e.g. 0.05 for 5%) applicable to this unit's rent.
     *
     * Residential is exempt; commercial and industrial are both standard-rated
     * at 5%; mixed-use is a configurable split (office policy, not statute)
     * rather than a fixed number — kept in Settings so it can change without
     * a deploy.
     */
    public function vatRate(): float
    {
        return match ($this->getAttribute('property_classification')) {
            PropertyClassification::RESIDENTIAL => 0.0,
            PropertyClassification::COMMERCIAL, PropertyClassification::INDUSTRIAL => 0.05,
            PropertyClassification::MIXED_USE => (float) Setting::get('pms_mixed_use_vat_rate', 0.05),
        };
    }

    public function isTaxable(): bool
    {
        return $this->vatRate() > 0.0;
    }
}
