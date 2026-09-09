<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One employee's pay for one month, frozen at the figures used.
 */
class Payslip extends Model
{
    use LogsActivity;

    protected $fillable = [
        'payroll_run_id',
        'party_id',
        'basic_snapshot',
        'allowances_snapshot',
        'incentive_amount',
        'incentive_overridden',
        'gross',
        'unpaid_days',
        'unpaid_deduction',
        'loan_deduction',
        'manual_deduction',
        'total_deductions',
        'net_pay',
        'eosg_accrued',
        'iban_snapshot',
        'bank_name_snapshot',
        'needs_review',
        'review_note',
    ];

    protected $casts = [
        'basic_snapshot' => 'decimal:2',
        'allowances_snapshot' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'gross' => 'decimal:2',
        'unpaid_days' => 'decimal:1',
        'unpaid_deduction' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'manual_deduction' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'eosg_accrued' => 'decimal:2',
        'incentive_overridden' => 'boolean',
        'needs_review' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * The daily rate unpaid leave is charged at: basic over a flat 30 days,
     * regardless of the month's actual length, per Article 51's convention.
     */
    public function dailyBasicRate(): float
    {
        return round((float) $this->getAttribute('basic_snapshot') / 30, 2);
    }
}
