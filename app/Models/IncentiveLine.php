<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class IncentiveLine extends Model
{
    use LogsActivity;

    protected $fillable = [
        'incentive_calculation_id',
        'matter_id',
        'fee_id',
        'completion_days',
        'difficulty',
        'fee_amount_excl_vat',
        'base_percentage',
        'committee_adjustment',
        'effective_percentage',
        'base_amount',
        'review_deduction_pct',
        'final_report_deduction_pct',
        'total_deduction_pct',
        'net_amount',
    ];

    protected $casts = [
        'completion_days' => 'integer',
        'fee_amount_excl_vat' => 'decimal:2',
        'base_percentage' => 'decimal:2',
        'committee_adjustment' => 'decimal:2',
        'effective_percentage' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'review_deduction_pct' => 'decimal:2',
        'final_report_deduction_pct' => 'decimal:2',
        'total_deduction_pct' => 'decimal:2',
        'net_amount' => 'decimal:2',
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

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(IncentiveLineDeduction::class);
    }

    public function assistantLines(): HasMany
    {
        return $this->hasMany(IncentiveAssistantLine::class);
    }
}
