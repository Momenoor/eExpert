<?php

namespace App\Models;

use App\Enums\PMS\InstallmentPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One collection recorded against an `Installment` — the ledger row
 * `PaymentService::recordPayment()` creates each time money comes in, so
 * a partially paid installment's history stays visible instead of being
 * overwritten by the next payment.
 */
class InstallmentPayment extends Model
{
    protected $fillable = [
        'installment_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'bank_name',
        'paid_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => InstallmentPaymentMethod::class,
        'paid_date' => 'date',
    ];

    /**
     * @return BelongsTo<Installment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }
}
