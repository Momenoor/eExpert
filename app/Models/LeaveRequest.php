<?php

namespace App\Models;

use App\Enums\LeaveType;
use App\Enums\RequestStatus;
use App\Observers\LeaveRequestObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An employee's application for time off.
 *
 * The request carries the outer date range and the approval trail; the typed
 * breakdown lives in its periods, so a single ten-day absence can be three days
 * annual, two casual and five unpaid without becoming three separate requests
 * for the approver to read.
 */
#[ObservedBy(LeaveRequestObserver::class)]
class LeaveRequest extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'party_id',
        'requested_by',
        'status',
        'start_date',
        'end_date',
        'comment',
        'requested_leave_type',
        'approved_by',
        'approved_at',
        'approved_comment',
    ];

    protected $casts = [
        'status' => RequestStatus::class,
        'requested_leave_type' => LeaveType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<LeaveRequestPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(LeaveRequestPeriod::class);
    }

    public function isPending(): bool
    {
        return $this->getAttribute('status') === RequestStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->getAttribute('status') === RequestStatus::APPROVED;
    }

    /**
     * Days the employee asked for, counted from the request's own dates.
     *
     * Distinct from totalDays(), which counts what the approver granted. Before
     * approval there are no periods at all, so this is the only figure the queue
     * can show.
     */
    public function requestedDays(): float
    {
        $start = $this->getAttribute('start_date');
        $end = $this->getAttribute('end_date');

        if ($start === null || $end === null) {
            return 0.0;
        }

        return (float) ($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
    }

    /**
     * Total days across every granted period, paid and unpaid alike.
     */
    public function totalDays(): float
    {
        return (float) $this->periods->sum('day_count');
    }

    /**
     * The days payroll will withhold: a half-pay sick day counts as half a day.
     */
    public function unpaidDays(): float
    {
        return (float) $this->periods->sum(
            fn (LeaveRequestPeriod $period): float => $period->unpaidDays(),
        );
    }
}
