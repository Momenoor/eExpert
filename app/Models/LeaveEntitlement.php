<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An employee's balances for one year of service.
 */
class LeaveEntitlement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'service_year_start',
        'annual_entitled_days',
        'annual_taken_days',
        'sick_full_taken',
        'sick_half_taken',
        'sick_unpaid_taken',
    ];

    protected $casts = [
        'service_year_start' => 'date',
        'annual_entitled_days' => 'decimal:1',
        'annual_taken_days' => 'decimal:1',
        'sick_full_taken' => 'decimal:1',
        'sick_half_taken' => 'decimal:1',
        'sick_unpaid_taken' => 'decimal:1',
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

    public function annualRemaining(): float
    {
        return (float) $this->getAttribute('annual_entitled_days')
            - (float) $this->getAttribute('annual_taken_days');
    }
}
