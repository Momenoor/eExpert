<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A month's movement in one employee's end-of-service liability.
 */
class EosgAccrual extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'period',
        'service_days',
        'basic_snapshot',
        'accrued_this_month',
        'cumulative_liability',
    ];

    protected $casts = [
        'service_days' => 'integer',
        'basic_snapshot' => 'decimal:2',
        'accrued_this_month' => 'decimal:2',
        'cumulative_liability' => 'decimal:2',
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
}
