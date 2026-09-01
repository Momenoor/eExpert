<?php

namespace App\Models;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Fee extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $fillable = [
        'matter_id',
        'user_id',
        'type',
        'amount',
        'date',
        'description',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => FeeStatus::class,
        'amount' => 'decimal:2',
        'type' => FeeType::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Fee $fee) {
            $fee->allocations()->delete();
        });

        static::saving(function (Fee $fee) {
            $fee->user_id = auth()->id();
            if (! $fee->date) {
                $fee->date = now();
            }
            if ($fee->type?->isNegative() && $fee->amount > 0) {
                $fee->amount = -abs($fee->amount);
            }
            if ($fee->type === FeeType::COURT_PENALITY) {
                $fee->matter->update(['has_court_penalty' => true]);
            }
        });

        static::saved(function (Fee $fee) {
            $fee->matter?->updateCollectionStatus();
        });

        static::deleted(function (Fee $fee) {
            if ($fee->type === FeeType::COURT_PENALITY) {
                // Only clear if no other court penalty fees remain
                $hasOtherPenalties = $fee->matter->fees()
                    ->where('type', FeeType::COURT_PENALITY)
                    ->where('id', '!=', $fee->id)
                    ->exists();

                if (! $hasOtherPenalties) {
                    $fee->matter->update(['has_court_penalty' => false]);
                }
            }
            $fee->matter?->updateCollectionStatus();
        });
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return HasMany<Allocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incentiveLines(): HasMany
    {
        return $this->hasMany(IncentiveLine::class);
    }

    public function getTotalAllocatedAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->total_allocated);
    }

    /**
     * How much a revenue fee may legitimately hold above its own amount.
     *
     * On a commission matter the client's gross payment is allocated to the
     * revenue fee while the office-share line carries the offsetting negative,
     * so the revenue line holds (fee + commission) and nets out correctly at
     * matter level. Without this allowance every one of those fees reads as
     * overpaid — ~405 of them in production — the moment updateStatus() runs.
     *
     * Deduction fees get no allowance: they are the offset, not the offsettee.
     */
    private function offsetAllowance(): float
    {
        if ($this->type?->isNegative() ?? false) {
            return 0.0;
        }

        if (! $this->matter_id) {
            return 0.0;
        }

        return (float) static::query()
            ->where('matter_id', $this->matter_id)
            ->whereIn('type', FeeType::deductionTypeValues())
            ->sum(DB::raw('ABS(amount)'));
    }

    /**
     * Update the fee status based on allocations.
     *
     * Compares magnitudes so the ladder reads identically for deduction fees,
     * which are stored negative and paid down negatively — previously a fully
     * settled -750 office share reported UNPAID because its allocation was
     * also negative.
     */
    public function updateStatus(): void
    {
        $this->syncStatus();

        if ($this->isDirty('status')) {
            $this->save();
        }

        // Also update matter collection status
        $this->matter?->updateCollectionStatus();
    }

    /**
     * Compute and set the status in memory without persisting anything.
     *
     * Split out from updateStatus() so a diagnostic can ask "what would this
     * become?" without writing to live financial records.
     */
    public function syncStatus(): void
    {
        $allocated = abs((float) ($this->relationLoaded('allocations')
            ? $this->allocations->sum('amount')
            : $this->allocations()->sum('amount')));

        $total = abs((float) $this->amount);
        $allowance = $this->offsetAllowance();

        // Money is decimal(15,2); compare with a cent of tolerance rather than
        // ==, which could never reliably be true for a float sum.
        $epsilon = 0.005;

        $this->status = match (true) {
            $allocated < $epsilon => FeeStatus::UNPAID,
            $allocated < $total - $epsilon => FeeStatus::PARTIAL,
            $allocated <= $total + $allowance + $epsilon => FeeStatus::PAID,
            default => FeeStatus::OVERPAID,
        };
    }
}
