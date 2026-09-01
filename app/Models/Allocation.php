<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Allocation extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $fillable = [
        'fee_id',
        'matter_id',
        'user_id',
        'amount',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Allocation $allocation) {
            $allocation->user_id = auth()->id();
            if (! $allocation->date) {
                $allocation->date = now();
            }

            // Automatically set matter_id from the parent fee
            if (! $allocation->matter_id && $allocation->fee_id) {
                $allocation->matter_id = $allocation->fee?->matter_id;
            }

            // Match the fee's direction. Fee::saving() already forces
            // deduction-type fees negative, but nothing did the same for their
            // allocations — CollectFeeAction flipped the sign by hand, so any
            // other write path could leave a negative fee with a positive
            // payment against it. That mismatch is exactly what the legacy
            // office-share rows show.
            $fee = $allocation->fee;

            if ($fee?->type?->isNegative() && (float) $allocation->amount > 0) {
                $allocation->amount = -abs((float) $allocation->amount);
            }
        });

        static::saved(function (Allocation $allocation) {
            $allocation->fee?->updateStatus();
            $allocation->matter?->updateCollectionStatus();
        });

        static::deleted(function (Allocation $allocation) {
            $allocation->fee?->updateStatus();
            $allocation->matter?->updateCollectionStatus();
        });
    }

    /**
     * @return BelongsTo<Fee, $this>
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
