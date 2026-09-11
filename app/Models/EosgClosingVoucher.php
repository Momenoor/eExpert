<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One year's annual EOSG closing entry, saved once generated.
 *
 * Generating replaces this row and its lines wholesale — it is a snapshot of
 * what the payslips said at the moment someone pressed the button, not a live
 * view. A payroll correction made after the year is closed does not silently
 * reshape it; someone has to press Generate again for that.
 */
class EosgClosingVoucher extends Model
{
    use LogsActivity;

    protected $fillable = [
        'year',
        'total_amount',
        'generated_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return HasMany<EosgClosingVoucherLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EosgClosingVoucherLine::class);
    }
}
