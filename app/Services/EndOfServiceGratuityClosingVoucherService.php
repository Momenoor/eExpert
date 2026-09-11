<?php

namespace App\Services;

use App\Enums\PayslipLineKind;
use App\Models\EosgClosingVoucher;
use App\Models\EosgClosingVoucherLine;
use App\Models\PayslipLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The annual EOSG closing journal voucher, generated and saved once a year at
 * 31/12 — not recomputed on the fly, unlike the monthly Salaries voucher.
 *
 * Every payroll run still computes and stores that month's gratuity accrual on
 * its payslips (`PayslipLine::EMPLOYER_COST`, via `PayrollService::accrueGratuity()`)
 * — the monthly figure stays accurate under mid-year salary changes because it is
 * a telescoping difference of cumulative entitlement. What this service does is
 * sum a calendar year's worth of those stored lines into one closing entry and
 * SAVE it (`EosgClosingVoucher` + `EosgClosingVoucherLine`), rather than the
 * monthly Salaries voucher (`PayrollJournalVoucherService`) posting a slice of
 * it every month.
 *
 *   Dr  Employer EOSG accrual expense   — itemised per employee
 *     Cr  EOSG provision (liability)    — the year's total, in one line
 *
 * Saving it is deliberate: once a year is closed, a payroll correction made
 * months later must not silently reshape what accounting already posted.
 * `generate()` is the only thing that changes the saved figures, and it
 * replaces the year's row and lines wholesale rather than patching them.
 *
 * An employee whose profile has `is_eosg_applicable` set to false never accrues
 * anything in the first place (`PayrollService::accrueGratuity()` returns 0 for
 * them), so excluding them again here is a defensive second check rather than
 * the only one.
 */
class EndOfServiceGratuityClosingVoucherService
{
    /**
     * @param  list<array{account: string, detail: string|null, amount: float}>  $debits
     * @return array{
     *     period: string,
     *     debits: list<array{account: string, detail: string|null, amount: float}>,
     *     credits: list<array{account: string, detail: string|null, amount: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     balanced: bool,
     *     employee_count: int,
     * }
     */
    private function shape(string $period, array $debits, int $employeeCount): array
    {
        $totalDebit = round((float) array_sum(array_column($debits, 'amount')), 2);

        $credits = $totalDebit > 0
            ? [['account' => PayrollJournalVoucherService::GL_EOSG_PROVISION, 'detail' => null, 'amount' => $totalDebit]]
            : [];

        return [
            'period' => $period,
            'debits' => $debits,
            'credits' => $credits,
            'total_debit' => $totalDebit,
            'total_credit' => $totalDebit,
            'balanced' => true,
            'employee_count' => $employeeCount,
        ];
    }

    /**
     * The saved voucher for a year, or null if nobody has generated one yet.
     *
     * Reads only what `generate()` last wrote — it never recomputes from
     * payslips, so it stays exactly what was posted even if a payroll run in
     * that year is corrected afterwards.
     *
     * @return array{
     *     period: string,
     *     debits: list<array{account: string, detail: string|null, amount: float}>,
     *     credits: list<array{account: string, detail: string|null, amount: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     balanced: bool,
     *     employee_count: int,
     *     generated_at: string,
     * }|null
     */
    public function forYear(int $year): ?array
    {
        $voucher = EosgClosingVoucher::query()
            ->where('year', $year)
            ->with('lines.party')
            ->first();

        if (! $voucher) {
            return null;
        }

        $debits = $voucher->lines
            ->map(fn (EosgClosingVoucherLine $line): array => [
                'account' => PayrollService::GL_EOSG_EXPENSE,
                'detail' => $line->party->name,
                'amount' => (float) $line->amount,
            ])
            ->sortBy('detail')
            ->values()
            ->all();

        return [
            ...$this->shape((string) __(':year — Annual Closing', ['year' => $year]), $debits, $voucher->lines->count()),
            'generated_at' => $voucher->generated_at->toIso8601String(),
        ];
    }

    /**
     * Recompute a calendar year from the payslips and save it, replacing
     * whatever was saved for that year before.
     */
    public function generate(int $year): EosgClosingVoucher
    {
        $lines = PayslipLine::query()
            ->where('kind', PayslipLineKind::EMPLOYER_COST)
            ->whereHas('payslip.payrollRun', fn ($query) => $query
                ->whereBetween('period', ["{$year}-01", "{$year}-12"]))
            ->whereHas('payslip.party.employeeProfile', fn ($query) => $query
                ->where('is_eosg_applicable', true))
            ->with('payslip.party')
            ->get();

        $byParty = $lines->groupBy(fn (PayslipLine $line): int => $line->payslip->party->getKey());

        $amountsByParty = $byParty
            ->map(fn (Collection $partyLines): float => round((float) $partyLines->sum('amount'), 2))
            ->filter(fn (float $amount): bool => $amount > 0);

        $totalAmount = round((float) $amountsByParty->sum(), 2);

        return DB::transaction(function () use ($year, $amountsByParty, $totalAmount): EosgClosingVoucher {
            $voucher = EosgClosingVoucher::updateOrCreate(
                ['year' => $year],
                ['total_amount' => $totalAmount, 'generated_at' => now()],
            );

            // Replaced wholesale rather than diffed — a year that shrinks to
            // fewer employees (an EOSG-applicable flag flipped off after the
            // fact, say) must not leave a stale line behind.
            $voucher->lines()->delete();

            $voucher->lines()->createMany(
                $amountsByParty->map(fn (float $amount, int $partyId): array => [
                    'party_id' => $partyId,
                    'amount' => $amount,
                ])->values()->all(),
            );

            return $voucher;
        });
    }
}
