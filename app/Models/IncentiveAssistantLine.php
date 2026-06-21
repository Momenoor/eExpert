<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class IncentiveAssistantLine extends Model
{
    use LogsActivity;

    protected $fillable = [
        'incentive_line_id',
        'party_id',
        'share_amount',
        'extra_percentage',
        'extra_amount',
        'minimum_penalty_pct',
        'minimum_penalty_amount',
        'total_amount',
    ];

    protected $casts = [
        'share_amount' => 'decimal:2',
        'extra_percentage' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'minimum_penalty_pct' => 'decimal:2',
        'minimum_penalty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function incentiveLine(): BelongsTo
    {
        return $this->belongsTo(IncentiveLine::class);
    }

    public function line(): BelongsTo
    {
        return $this->incentiveLine();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
