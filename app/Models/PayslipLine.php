<?php

namespace App\Models;

use App\Enums\PayslipLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One itemised line on a payslip, tagged with the account it posts to.
 */
class PayslipLine extends Model
{
    use LogsActivity;

    protected $fillable = [
        'payslip_id',
        'kind',
        'label',
        'amount',
        'gl_account',
    ];

    protected $casts = [
        'kind' => PayslipLineKind::class,
        'amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
