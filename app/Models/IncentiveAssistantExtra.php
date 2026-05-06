<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;


class IncentiveAssistantExtra extends Model
{
    use LogsActivity;

    protected $fillable = [
        'incentive_calculation_id',
        'party_id',
        'completed_matter_count',
        'meets_minimum',
        'minimum_penalty_pct',
        'extra_percentage',
        'extra_amount',
        'penalty_amount',
    ];

    protected $casts = [
        'completed_matter_count' => 'integer',
        'meets_minimum' => 'boolean',
        'minimum_penalty_pct' => 'decimal:2',
        'extra_percentage' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(IncentiveCalculation::class, 'incentive_calculation_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
