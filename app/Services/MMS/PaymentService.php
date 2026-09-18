<?php

namespace App\Services\MMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\Setting;
use RuntimeException;

/**
 * Recording money against an instalment, and the two abnormal paths —
 * bounced cheques and the overdue flag — that don't go through a payment at
 * all.
 */
class PaymentService
{
    /**
     * @param  array{
     *     amount: float|string,
     *     payment_method?: string|null,
     *     transaction_reference?: string|null,
     *     paid_date?: string|null,
     * }  $data
     */
    public function recordPayment(Installment $installment, array $data): Installment
    {
        if ($installment->isPaid()) {
            throw new RuntimeException('This instalment is already fully paid.');
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0.0) {
            throw new RuntimeException('A payment must be greater than zero.');
        }

        $paymentMethod = $data['payment_method'] ?? $installment->getAttribute('payment_method');
        $transactionReference = $data['transaction_reference'] ?? $installment->getAttribute('transaction_reference');
        $paidDate = $data['paid_date'] ?? now()->toDateString();

        InstallmentPayment::create([
            'installment_id' => $installment->getKey(),
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionReference,
            'paid_date' => $paidDate,
        ]);

        // `paid_amount` is always the sum of the ledger, not a running
        // total kept in sync by hand — the ledger is the source of truth.
        $paidAmount = round((float) $installment->payments()->sum('amount'), 2);
        $totalDue = round(
            (float) $installment->getAttribute('total_due_amount') + (float) $installment->getAttribute('admin_penalty_amount'),
            2,
        );
        $balance = max(0.0, round($totalDue - $paidAmount, 2));

        $installment->forceFill([
            'paid_amount' => $paidAmount,
            'balance_due' => $balance,
            'payment_status' => $balance <= 0.0 ? InstallmentPaymentStatus::PAID : InstallmentPaymentStatus::PARTIAL,
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionReference,
            'paid_date' => $paidDate,
        ])->save();

        return $installment;
    }

    /**
     * A cheque that failed to clear: whatever it had contributed reverts —
     * the money never actually arrived — and an administrative penalty is
     * added on top of the rent still owed. Past `InstallmentPayment` ledger
     * rows are left in place as a record of what was attempted; only the
     * installment's own running totals are reset.
     */
    public function markBounced(Installment $installment): Installment
    {
        if ($installment->isPaid()) {
            throw new RuntimeException('A fully paid instalment cannot be marked bounced.');
        }

        $penalty = round((float) Setting::get('pms_bounced_cheque_penalty', 100.0), 2);
        $balance = round((float) $installment->getAttribute('total_due_amount') + $penalty, 2);

        $installment->forceFill([
            'payment_status' => InstallmentPaymentStatus::BOUNCED,
            'paid_amount' => 0,
            'admin_penalty_amount' => $penalty,
            'balance_due' => $balance,
        ])->save();

        return $installment;
    }

    /**
     * The one rule the scheduled overdue flagger applies: past its own grace
     * period, not paid, not already flagged.
     */
    public function flagOverdueIfNeeded(Installment $installment): void
    {
        if ($installment->isPaid() || $installment->isOverdue()) {
            return;
        }

        if ($installment->isPastGracePeriod()) {
            $installment->forceFill(['payment_status' => InstallmentPaymentStatus::OVERDUE])->save();
        }
    }
}
