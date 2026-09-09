<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One scheduled monthly recovery against a loan.
 */
class LoanInstallment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'employee_loan_id',
        'seq',
        'due_period',
        'amount',
        'payslip_id',
    ];

    protected $casts = [
        'seq' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return BelongsTo<EmployeeLoan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function isDeducted(): bool
    {
        return $this->getAttribute('payslip_id') !== null;
    }
}
