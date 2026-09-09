<?php

namespace App\Models;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A salary advance and the schedule recovering it.
 */
class EmployeeLoan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'party_id',
        'kind',
        'principal',
        'months',
        'starts_on',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'kind' => LoanKind::class,
        'status' => LoanStatus::class,
        'principal' => 'decimal:2',
        'months' => 'integer',
        'starts_on' => 'date',
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * What is still owed: the sum of instalments no payslip has taken yet.
     */
    public function outstanding(): float
    {
        return (float) $this->installments()->whereNull('payslip_id')->sum('amount');
    }

    public function isSettled(): bool
    {
        return $this->getAttribute('status') === LoanStatus::SETTLED;
    }

    /**
     * Whether payroll has already recovered part of this advance.
     */
    public function hasDeductedInstallments(): bool
    {
        return $this->installments()->whereNotNull('payslip_id')->exists();
    }

    /**
     * Whether the advance may still be altered.
     *
     * Once a single instalment has been taken, the amount and the term are part
     * of a payslip somebody has approved. Changing them would rewrite a schedule
     * that has already been partly recovered, and the remaining instalments would
     * no longer add up to what is actually owed.
     */
    public function isEditable(): bool
    {
        return ! $this->hasDeductedInstallments();
    }
}
