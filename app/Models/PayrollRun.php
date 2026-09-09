<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One month of payroll and the sign-offs it collected.
 */
class PayrollRun extends Model
{
    use LogsActivity;

    protected $fillable = [
        'period',
        'name',
        'status',
        'hr_approved_by',
        'hr_approved_at',
        'finance_approved_by',
        'finance_approved_at',
        'disbursed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => PayrollRunStatus::class,
        'hr_approved_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function financeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by');
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function isEditable(): bool
    {
        return $this->getAttribute('status')->isEditable();
    }

    /**
     * First and last day of the period this run covers.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dateRange(): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $this->getAttribute('period').'-01')
            ->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }
}
