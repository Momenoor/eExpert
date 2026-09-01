<?php

namespace App\Models;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Fee extends Model
{
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
     * Update the fee status based on allocations.
     */
    public function updateStatus(): void
    {
        $allocated = (float) $this->allocations()->sum('amount');
        $total = (float) $this->amount;

        if ($allocated <= 0) {
            $this->status = FeeStatus::UNPAID;
        } elseif ($allocated < $total) {
            $this->status = FeeStatus::PARTIAL;
        } elseif ($allocated == $total) {
            $this->status = FeeStatus::PAID;
        } else {
            $this->status = FeeStatus::OVERPAID;
        }

        if ($this->isDirty('status')) {
            $this->save();
        }

        // Also update matter collection status
        $this->matter?->updateCollectionStatus();
    }
}
