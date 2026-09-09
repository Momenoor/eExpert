<?php

namespace App\Models;

use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One typed slice of a leave request.
 */
class LeaveRequestPeriod extends Model
{
    use LogsActivity;

    protected $fillable = [
        'leave_request_id',
        'leave_type',
        'start_date',
        'end_date',
        'day_count',
        'pay_factor',
    ];

    protected $casts = [
        'leave_type' => LeaveType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'day_count' => 'decimal:1',
        'pay_factor' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // The factor is derived from the type, not typed in. Leaving it to the
        // UI would let an approver hand-edit an unpaid absence into a paid one
        // without that showing up as a change of leave type.
        static::saving(function (LeaveRequestPeriod $period): void {
            $type = $period->getAttribute('leave_type');

            if ($type instanceof LeaveType) {
                $period->setAttribute('pay_factor', $type->payFactor());
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return HasOne<PartyLeave, $this>
     */
    public function partyLeave(): HasOne
    {
        return $this->hasOne(PartyLeave::class);
    }

    /**
     * Days of pay withheld by this period.
     */
    public function unpaidDays(): float
    {
        return (float) $this->getAttribute('day_count')
            * (1.0 - (float) $this->getAttribute('pay_factor'));
    }
}
