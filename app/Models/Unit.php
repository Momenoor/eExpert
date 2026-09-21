<?php

namespace App\Models;

use App\Enums\PMS\ContractType;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitStatus;
use App\Enums\PMS\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'rental_type',
        'status',
    ];

    protected $casts = [
        'rental_rate' => 'decimal:2',
        'area_sqm' => 'decimal:2',
        'number_of_rooms' => 'integer',
        'property_classification' => PropertyClassification::class,
        'unit_type' => UnitType::class,
        'rental_type' => ContractType::class,
        'status' => UnitStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
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
