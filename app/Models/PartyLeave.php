<?php

namespace App\Models;

use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A stretch of days an employee was away.
 *
 * This is the single ledger the rest of the app reads: IncentiveCalculatorService
 * prorates monthly quotas from it, and payroll counts unpaid days from it. Leave
 * requests write a row here on approval rather than keeping their own record, so
 * neither consumer has to know the approval workflow exists — and so the rows
 * entered by hand before that workflow did keep working.
 */
class PartyLeave extends Model
{
    use LogsActivity;

    protected $fillable = [
        'party_id',
        'leave_request_period_id',
        'start_date',
        'end_date',
        'leave_type',
        'pay_factor',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'leave_type' => LeaveType::class,
        'pay_factor' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function leaveRequestPeriod(): BelongsTo
    {
        return $this->belongsTo(LeaveRequestPeriod::class);
    }

    /**
     * The share of a day's pay this leave earns.
     *
     * Rows predating the payroll module carry no factor. They are treated as
     * fully paid: they were recorded to explain an absence, not to dock anyone,
     * and reading them as unpaid would invent deductions out of history.
     */
    public function payFactor(): float
    {
        $factor = $this->getAttribute('pay_factor');

        return $factor === null ? 1.0 : (float) $factor;
    }
}
