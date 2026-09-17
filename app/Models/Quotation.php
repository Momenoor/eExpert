<?php

namespace App\Models;

use App\Enums\PMS\QuotationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An offer of one or more units to a prospective tenant, before any contract
 * exists. Status only ever moves forward through `QuotationService` — never
 * hand-edited on the record — so `isDraft()`/`isSent()` here are read-only
 * mirrors of what the service already enforced.
 */
class Quotation extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'base_rent',
        'vat_amount',
        'attestation_fee_estimate',
        'total_amount',
        'security_deposit',
        'number_of_installments',
        'validity_date',
        'status',
    ];

    protected $casts = [
        'base_rent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'attestation_fee_estimate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'number_of_installments' => 'integer',
        'validity_date' => 'date',
        'status' => QuotationStatus::class,
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
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'quotation_unit')
            ->withPivot('offered_rent', 'vat_amount')
            ->withTimestamps();
    }

    public function isDraft(): bool
    {
        return $this->getAttribute('status') === QuotationStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->getAttribute('status') === QuotationStatus::SENT;
    }

    public function isAccepted(): bool
    {
        return $this->getAttribute('status') === QuotationStatus::ACCEPTED;
    }

    public function hasExpired(): bool
    {
        $validityDate = $this->getAttribute('validity_date');

        return $validityDate !== null && $validityDate->isPast();
    }
}
