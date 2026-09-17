<?php

namespace App\Services\PMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use App\Models\Lease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Splitting a lease's rent into a payment schedule — one `Installment`
 * row per payment, each carrying its own frozen VAT rate and tax-invoice
 * fields, so a later change to the lease or its units can't silently
 * rewrite a schedule already handed to the tenant.
 */
class InstallmentGenerator
{
    /**
     * @return Collection<int, Installment>
     */
    public function generateSchedule(Lease $lease, int $count): Collection
    {
        if ($count < 1) {
            throw new RuntimeException('A lease must be split into at least one instalment.');
        }

        if ($lease->installments()->exists()) {
            throw new RuntimeException('This lease already has an instalment schedule.');
        }

        $totalRent = (float) $lease->getAttribute('total_base_rent');
        $vatRate = $lease->vatRate();
        $landlordTrn = $lease->landlordTrn();
        $tenantTrn = $lease->tenantTrn();

        $netAmounts = $this->splitEvenly($totalRent, $count);
        $dueDates = $this->spreadDueDates($lease, $count);

        return DB::transaction(function () use ($lease, $count, $netAmounts, $dueDates, $vatRate, $landlordTrn, $tenantTrn): Collection {
            $installments = collect();

            for ($i = 0; $i < $count; $i++) {
                $net = $netAmounts[$i];
                $vat = round($net * $vatRate, 2);
                $dueDate = $dueDates[$i];

                $installments->push(Installment::create([
                    'lease_id' => $lease->getKey(),
                    'due_date' => $dueDate,
                    'grace_period_expiry_date' => $dueDate->copy()->addDays((int) $lease->getAttribute('grace_period_days')),
                    'net_amount' => $net,
                    'vat_amount' => $vat,
                    'total_due_amount' => round($net + $vat, 2),
                    'admin_penalty_amount' => 0,
                    'paid_amount' => 0,
                    'balance_due' => round($net + $vat, 2),
                    'payment_status' => InstallmentPaymentStatus::PENDING,
                    'landlord_trn' => $landlordTrn,
                    'tenant_trn' => $tenantTrn,
                    'tax_invoice_serial' => sprintf('INV-%d-%02d', $lease->getKey(), $i + 1),
                    'date_of_supply' => $dueDate,
                    'vat_rate' => $vatRate,
                ]));
            }

            return $installments;
        });
    }

    /**
     * @return list<float>
     */
    private function splitEvenly(float $total, int $count): array
    {
        $amount = round($total / $count, 2);
        $amounts = array_fill(0, $count, $amount);

        // The last instalment absorbs whatever rounding drift the even split
        // leaves behind, so the schedule always sums to exactly the total.
        $amounts[$count - 1] = round($total - array_sum(array_slice($amounts, 0, $count - 1)), 2);

        return $amounts;
    }

    /**
     * @return list<Carbon>
     */
    private function spreadDueDates(Lease $lease, int $count): array
    {
        $start = $lease->getAttribute('start_date')->copy();
        $end = $lease->getAttribute('end_date')->copy();
        $totalDays = max(1, $start->diffInDays($end));
        $interval = $totalDays / $count;

        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $dates[] = $start->copy()->addDays((int) round($interval * $i));
        }

        return $dates;
    }
}
