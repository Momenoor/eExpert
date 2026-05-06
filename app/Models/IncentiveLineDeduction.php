<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;


class IncentiveLineDeduction extends Model
{
    use LogsActivity;

    protected $fillable = [
        'incentive_line_id',
        'type',
        'percentage',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
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
}
