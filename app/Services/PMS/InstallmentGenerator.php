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
        ['vatRate' => $vatRate, 'landlordTrn' => $landlordTrn, 'tenantTrn' => $tenantTrn] = $this->resolveTaxContext($lease);

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
     * A lease's own payment schedule, declared row by row by the office at
     * creation time instead of split evenly — the wizard's Installments
     * step already validated the rows' amounts sum to the right total
     * (rent + VAT + security deposit), so this only has to persist them.
     *
     * @param  list<array{
     *     payment_method: string,
     *     payment_date: string,
     *     amount: float|string,
     *     reference_number?: string|null,
     *     is_security_deposit?: bool,
     *     vat_handling?: string,
     * }>  $rows
     * @return Collection<int, Installment>
     */
    public function recordManualSchedule(Lease $lease, array $rows): Collection
    {
        if ($rows === []) {
            throw new RuntimeException('A lease must have at least one instalment.');
        }

        if ($lease->installments()->exists()) {
            throw new RuntimeException('This lease already has an instalment schedule.');
        }

        ['vatRate' => $vatRate, 'landlordTrn' => $landlordTrn, 'tenantTrn' => $tenantTrn] = $this->resolveTaxContext($lease);

        return DB::transaction(function () use ($lease, $rows, $vatRate, $landlordTrn, $tenantTrn): Collection {
            $installments = collect();

            foreach (array_values($rows) as $index => $row) {
                $amount = (float) $row['amount'];
                $isSecurityDeposit = (bool) ($row['is_security_deposit'] ?? false);
                $vatHandling = $isSecurityDeposit ? 'excluded' : ($row['vat_handling'] ?? 'combined');

                // A refundable security deposit sits outside the scope of
                // supply under UAE VAT law — it must never be VAT-loaded the
                // way a rent instalment is. Otherwise the office can declare
                // a row as VAT bundled into its own cheque ("combined", the
                // historical behaviour), as a rent-only cheque with the VAT
                // collected on a sibling row ("excluded"), or as that VAT
                // cheque itself ("vat_only").
                [$net, $vat] = match ($vatHandling) {
                    'excluded' => [$amount, 0.0],
                    'vat_only' => [0.0, $amount],
                    default => [round($amount / (1 + $vatRate), 2), round($amount - round($amount / (1 + $vatRate), 2), 2)],
                };
                $isVatOnly = $vatHandling === 'vat_only';
                $dueDate = Carbon::parse($row['payment_date']);

                $installments->push(Installment::create([
                    'lease_id' => $lease->getKey(),
                    'is_security_deposit' => $isSecurityDeposit,
                    'is_vat_only' => $isVatOnly,
                    'due_date' => $dueDate,
                    'grace_period_expiry_date' => $dueDate->copy()->addDays((int) $lease->getAttribute('grace_period_days')),
                    'net_amount' => $net,
                    'vat_amount' => $vat,
                    'total_due_amount' => round($net + $vat, 2),
                    'admin_penalty_amount' => 0,
                    'paid_amount' => 0,
                    'balance_due' => round($net + $vat, 2),
                    'payment_method' => $row['payment_method'],
                    'transaction_reference' => $row['reference_number'] ?? null,
                    'payment_status' => InstallmentPaymentStatus::PENDING,
                    'landlord_trn' => $landlordTrn,
                    'tenant_trn' => $tenantTrn,
                    'tax_invoice_serial' => $isSecurityDeposit ? null : sprintf('INV-%d-%02d', $lease->getKey(), $index + 1),
                    'date_of_supply' => $isSecurityDeposit ? null : $dueDate,
                    'vat_rate' => $isSecurityDeposit ? 0 : $vatRate,
                ]));
            }

            return $installments;
        });
    }

    /**
     * @return array{vatRate: float, landlordTrn: ?string, tenantTrn: ?string}
     */
    private function resolveTaxContext(Lease $lease): array
    {
        return [
            'vatRate' => $lease->vatRate(),
            'landlordTrn' => $lease->landlordTrn(),
            'tenantTrn' => $lease->tenantTrn(),
        ];
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
