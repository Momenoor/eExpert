<?php

namespace Tests\Feature;

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\EosgClosingVoucher;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Services\EndOfServiceGratuityClosingVoucherService;
use App\Services\EndOfServiceGratuityService;
use App\Services\PayrollJournalVoucherService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The annual EOSG closing voucher: one year's worth of monthly accruals,
 * summed into a single entry, itemised per employee — generated on demand and
 * saved, not recomputed live on every view.
 */
class EndOfServiceGratuityClosingVoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    private EndOfServiceGratuityClosingVoucherService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EndOfServiceGratuityClosingVoucherService::class);
    }

    private function employee(array $profile = []): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create([
            'party_id' => $party->id,
            'date_of_joining' => '2020-01-01',
            ...$profile,
        ]);

        EmployeeSalaryComponent::create([
            'party_id' => $party->id,
            'component' => SalaryComponent::BASIC->value,
            'amount' => 6000,
            'effective_from' => '2020-01-01',
        ]);

        return $party->fresh();
    }

    private function generateMonth(string $period): PayrollRun
    {
        $run = PayrollRun::create(['period' => $period, 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        return $run;
    }

    /**
     * Every one of a year's twelve months, run through payroll — the only
     * way the real monthly figures are trusted instead of the synthetic
     * fallback (`hasCompletePayslipCoverage()` requires full coverage).
     */
    private function generateFullYear(int $year): void
    {
        foreach (range(1, 12) as $month) {
            $this->generateMonth(sprintf('%d-%02d', $year, $month));
        }
    }

    private function totalEosgAccruedIn(int $year): float
    {
        return (float) PayrollRun::query()
            ->where('period', 'like', "{$year}-%")
            ->get()
            ->sum(fn (PayrollRun $run) => (float) $run->payslips->sum('eosg_accrued'));
    }

    public function test_a_year_nobody_has_generated_returns_null(): void
    {
        $this->employee();
        $this->generateMonth('2026-01');

        // Accrued in the payslips, but nobody has pressed Generate — forYear()
        // never falls back to a live figure.
        $this->assertNull($this->service->forYear(2026));
    }

    public function test_generating_saves_a_voucher_and_its_lines(): void
    {
        $party = $this->employee();

        foreach (['2026-01', '2026-02', '2026-03'] as $period) {
            $this->generateMonth($period);
        }

        $saved = $this->service->generate(2026);

        $this->assertInstanceOf(EosgClosingVoucher::class, $saved);
        $this->assertSame(2026, $saved->year);
        $this->assertNotNull($saved->generated_at);
        $this->assertCount(1, $saved->lines);
        $this->assertSame($party->id, $saved->lines->first()->party_id);
        $this->assertEqualsWithDelta((float) $saved->total_amount, (float) $saved->lines->sum('amount'), 0.005);
    }

    public function test_for_year_reads_back_the_saved_voucher(): void
    {
        $party = $this->employee();

        foreach (['2026-01', '2026-02', '2026-03'] as $period) {
            $this->generateMonth($period);
        }

        $this->service->generate(2026);
        $voucher = $this->service->forYear(2026);

        $this->assertNotNull($voucher);
        $this->assertSame(1, $voucher['employee_count']);
        $this->assertTrue($voucher['balanced']);
        $this->assertSame($voucher['total_debit'], $voucher['total_credit']);
        $this->assertNotNull($voucher['generated_at']);

        $this->assertCount(1, $voucher['debits']);
        $this->assertSame(PayrollService::GL_EOSG_EXPENSE, $voucher['debits'][0]['account']);
        $this->assertSame($party->name, $voucher['debits'][0]['detail']);
        $this->assertGreaterThan(0, $voucher['debits'][0]['amount']);

        $this->assertCount(1, $voucher['credits']);
        $this->assertSame(PayrollJournalVoucherService::GL_EOSG_PROVISION, $voucher['credits'][0]['account']);
    }

    public function test_regenerating_replaces_the_saved_figures_wholesale(): void
    {
        $first = $this->employee();
        $this->generateMonth('2026-01');
        $this->service->generate(2026);

        // A second employee starts accruing later in the year — regenerating
        // must pick them up, not just refresh the first employee's line.
        $second = $this->employee();
        $this->generateMonth('2026-02');
        $voucher = $this->service->generate(2026);

        $this->assertCount(2, $voucher->fresh()->lines);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $voucher->lines()->pluck('party_id')->all(),
        );

        // Still one row for the year, not two.
        $this->assertSame(1, EosgClosingVoucher::where('year', 2026)->count());
    }

    public function test_it_excludes_an_employee_not_applicable_for_eosg(): void
    {
        $party = $this->employee(['is_eosg_applicable' => false]);
        $this->generateMonth('2026-01');

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertSame(0, $voucher['employee_count']);
        $this->assertNotContains($party->name, array_column($voucher['debits'], 'detail'));
    }

    public function test_it_excludes_accruals_from_other_years(): void
    {
        $this->employee();

        // Full coverage on both years — otherwise each year falls back to
        // the synthetic figure regardless of the other year's data.
        $this->generateFullYear(2025);
        $this->generateFullYear(2026);

        $accrued2025 = $this->totalEosgAccruedIn(2025);
        $accrued2026 = $this->totalEosgAccruedIn(2026);

        $this->service->generate(2025);
        $this->service->generate(2026);

        // Each year sees only the twelve months accrued inside it, not both.
        $voucher2025 = $this->service->forYear(2025);
        $voucher2026 = $this->service->forYear(2026);

        $this->assertEqualsWithDelta($accrued2025, $voucher2025['total_debit'], 0.01);
        $this->assertEqualsWithDelta($accrued2026, $voucher2026['total_debit'], 0.01);
    }

    public function test_a_partial_years_real_payslips_are_discarded_in_favor_of_the_synthetic_figure(): void
    {
        $party = $this->employee();

        // Only three months run through payroll — payroll started partway
        // through the year, or a run was later deleted. Either way this is
        // not a trustworthy annual figure.
        foreach (['2026-01', '2026-02', '2026-03'] as $period) {
            $this->generateMonth($period);
        }

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $gratuity = app(EndOfServiceGratuityService::class);
        $expectedSynthetic = $gratuity->accrualBetween(
            Carbon::parse('2020-01-01'),
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-12-31')->endOfDay(),
            6000.0,
        );

        // The three months' worth of real data is discarded entirely in
        // favour of the synthetic annual figure, not folded in or left as-is
        // (the partial sum is a fraction of a year's accrual, so it is nowhere
        // near the full-year synthetic figure asserted above).
        $this->assertSame($party->name, $voucher['debits'][0]['detail']);
        $this->assertEqualsWithDelta($expectedSynthetic, $voucher['debits'][0]['amount'], 0.01);
    }

    public function test_generating_a_year_with_nothing_accrued_saves_an_empty_but_balanced_voucher(): void
    {
        $voucher = $this->service->forYear($this->service->generate(2019)->year);

        $this->assertNotNull($voucher);
        $this->assertSame(0, $voucher['employee_count']);
        $this->assertSame(0.0, $voucher['total_debit']);
        $this->assertSame(0.0, $voucher['total_credit']);
        $this->assertTrue($voucher['balanced']);
        $this->assertSame([], $voucher['debits']);
        $this->assertSame([], $voucher['credits']);
    }

    public function test_multiple_employees_are_itemised_separately(): void
    {
        $first = $this->employee();
        $second = $this->employee();
        $this->generateMonth('2026-01');

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertSame(2, $voucher['employee_count']);
        $this->assertCount(2, $voucher['debits']);
        $this->assertEqualsCanonicalizing(
            [$first->name, $second->name],
            array_column($voucher['debits'], 'detail'),
        );
    }

    public function test_a_year_with_no_payslips_at_all_still_accrues_from_service_dates_and_year_end_salary(): void
    {
        // Joined well before the payroll module ever ran a payslip — this is
        // exactly the "office started using this system in 2026" scenario.
        $party = $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $gratuity = app(EndOfServiceGratuityService::class);
        $expected = $gratuity->accrualBetween(
            Carbon::parse('2020-01-01'),
            Carbon::parse('2023-01-01')->startOfDay(),
            Carbon::parse('2023-12-31')->endOfDay(),
            6000.0,
        );

        $this->assertSame(1, $voucher['employee_count']);
        $this->assertSame($party->name, $voucher['debits'][0]['detail']);
        $this->assertEqualsWithDelta($expected, $voucher['debits'][0]['amount'], 0.01);
        $this->assertGreaterThan(0, $voucher['total_debit']);
    }

    public function test_an_employee_who_joined_after_the_year_ended_accrues_nothing_synthetically(): void
    {
        $this->employee(['date_of_joining' => '2024-06-01']);

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $this->assertSame(0, $voucher['employee_count']);
    }

    public function test_an_employee_who_left_before_the_year_started_accrues_nothing_synthetically(): void
    {
        $this->employee(['date_of_leaving' => '2022-06-01']);

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $this->assertSame(0, $voucher['employee_count']);
    }

    public function test_the_synthetic_fallback_also_respects_the_eosg_applicable_flag(): void
    {
        $this->employee(['is_eosg_applicable' => false]);

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $this->assertSame(0, $voucher['employee_count']);
    }

    public function test_no_salary_on_record_for_that_year_accrues_nothing_synthetically(): void
    {
        $party = Party::factory()->employee()->create();
        EmployeeProfile::create(['party_id' => $party->id, 'date_of_joining' => '2020-01-01']);
        // No EmployeeSalaryComponent at all — nothing to base a figure on.

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $this->assertSame(0, $voucher['employee_count']);
    }

    public function test_real_payslip_data_takes_priority_over_the_synthetic_fallback_in_a_mixed_year(): void
    {
        // Has a complete year of payslips for 2026 — must use their sum, not
        // the synthetic year-end estimate.
        $withPayslip = $this->employee();
        $this->generateFullYear(2026);
        $realAccrual = $this->totalEosgAccruedIn(2026);

        // Joined the same day, same salary, but created after the year was
        // already run — never appears on a payslip, so falls back to the
        // synthetic figure.
        $withoutPayslip = $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertSame(2, $voucher['employee_count']);

        $amountsByName = array_combine(
            array_column($voucher['debits'], 'detail'),
            array_column($voucher['debits'], 'amount'),
        );

        $this->assertEqualsWithDelta($realAccrual, $amountsByName[$withPayslip->name], 0.01);
        $this->assertArrayHasKey($withoutPayslip->name, $amountsByName);
    }

    public function test_the_opening_balance_is_added_on_top_of_the_first_voucher_generated(): void
    {
        $this->employee(['opening_eosg_balance' => 5000]);
        $this->generateFullYear(2026);
        $realAccrual = $this->totalEosgAccruedIn(2026);

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertEqualsWithDelta($realAccrual + 5000, $voucher['debits'][0]['amount'], 0.01);
    }

    public function test_the_opening_balance_is_not_added_again_on_a_later_year(): void
    {
        $this->employee(['opening_eosg_balance' => 5000]);
        $this->generateFullYear(2026);
        $this->service->generate(2026);

        $this->generateFullYear(2027);
        $voucherFor2027 = $this->service->forYear($this->service->generate(2027)->year);

        $realAccrual2027 = $this->totalEosgAccruedIn(2027);

        $this->assertEqualsWithDelta($realAccrual2027, $voucherFor2027['debits'][0]['amount'], 0.01);
    }

    public function test_regenerating_the_same_year_keeps_the_opening_balance(): void
    {
        $this->employee(['opening_eosg_balance' => 5000]);
        $this->generateFullYear(2026);

        $this->service->generate(2026);
        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $realAccrual = $this->totalEosgAccruedIn(2026);

        $this->assertEqualsWithDelta($realAccrual + 5000, $voucher['debits'][0]['amount'], 0.01);
    }

    public function test_the_opening_balance_alone_creates_a_line_even_with_nothing_else_accrued(): void
    {
        // Left long before 2026, so no real or synthetic accrual — only the
        // opening balance should appear.
        $party = $this->employee([
            'date_of_joining' => '2015-01-01',
            'date_of_leaving' => '2019-01-01',
            'opening_eosg_balance' => 3000,
        ]);

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertSame(1, $voucher['employee_count']);
        $this->assertSame($party->name, $voucher['debits'][0]['detail']);
        $this->assertSame(3000.0, $voucher['debits'][0]['amount']);
    }

    public function test_no_opening_balance_leaves_the_figure_untouched(): void
    {
        $this->employee();
        $this->generateFullYear(2026);

        $realAccrual = $this->totalEosgAccruedIn(2026);

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertEqualsWithDelta($realAccrual, $voucher['debits'][0]['amount'], 0.01);
    }

    public function test_the_rollforward_reconciles_opening_plus_movement_to_closing(): void
    {
        $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $row = $voucher['rollforward'][0];

        $this->assertEqualsWithDelta(
            $row['closing_balance'],
            $row['opening_balance'] + $row['current_year_amount'],
            0.01,
        );
    }

    public function test_a_first_generated_year_computes_opening_balance_from_service_dates(): void
    {
        $party = $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $gratuity = app(EndOfServiceGratuityService::class);
        $expectedOpening = $gratuity->gratuityAsOf(
            Carbon::parse('2020-01-01'),
            Carbon::parse('2022-12-31')->startOfDay(),
            6000.0,
        );

        $row = $voucher['rollforward'][0];

        $this->assertSame($party->name, $row['party_name']);
        $this->assertEqualsWithDelta($expectedOpening, $row['opening_balance'], 0.01);
    }

    public function test_a_later_years_opening_balance_chains_from_the_prior_years_closing(): void
    {
        $this->employee();

        $firstYear = $this->service->forYear($this->service->generate(2023)->year);
        $secondYear = $this->service->forYear($this->service->generate(2024)->year);

        $this->assertEqualsWithDelta(
            $firstYear['rollforward'][0]['closing_balance'],
            $secondYear['rollforward'][0]['opening_balance'],
            0.01,
        );
    }

    public function test_an_employee_who_left_during_the_year_is_flagged_with_their_leaving_date(): void
    {
        $this->employee(['date_of_leaving' => '2026-06-15']);

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $row = $voucher['rollforward'][0];

        $this->assertTrue($row['left_during_year']);
        $this->assertSame('2026-06-15', $row['date_of_leaving']);

        // Their closing balance is their entitlement as of leaving, not 31/12.
        $gratuity = app(EndOfServiceGratuityService::class);
        $expectedClosing = $row['opening_balance'] + $gratuity->accrualBetween(
            Carbon::parse('2020-01-01'),
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-06-15'),
            6000.0,
        );

        $this->assertEqualsWithDelta($expectedClosing, $row['closing_balance'], 0.01);
    }

    public function test_an_employee_still_employed_is_not_flagged_as_having_left(): void
    {
        $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2023)->year);

        $row = $voucher['rollforward'][0];

        $this->assertFalse($row['left_during_year']);
        $this->assertNull($row['date_of_leaving']);
    }

    public function test_payment_status_defaults_to_unpaid(): void
    {
        $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2023)->year);
        $row = $voucher['rollforward'][0];

        $this->assertSame(__('Unpaid'), $row['payment_status']);
        $this->assertSame(0.0, $row['paid_amount']);
        $this->assertEqualsWithDelta($row['closing_balance'], $row['outstanding'], 0.01);
    }

    public function test_payment_status_partially_paid(): void
    {
        $party = $this->employee();
        $this->service->generate(2023);

        $closingBalance = (float) $this->service->forYear(2023)['rollforward'][0]['closing_balance'];
        $party->employeeProfile->forceFill(['eosg_paid_amount' => $closingBalance / 2])->save();

        $row = $this->service->forYear(2023)['rollforward'][0];

        $this->assertSame(__('Partially Paid'), $row['payment_status']);
        $this->assertEqualsWithDelta($closingBalance / 2, $row['paid_amount'], 0.01);
        $this->assertEqualsWithDelta($closingBalance / 2, $row['outstanding'], 0.01);
    }

    public function test_payment_status_paid_in_full(): void
    {
        $party = $this->employee();
        $this->service->generate(2023);

        $closingBalance = (float) $this->service->forYear(2023)['rollforward'][0]['closing_balance'];
        $party->employeeProfile->forceFill(['eosg_paid_amount' => $closingBalance])->save();

        $row = $this->service->forYear(2023)['rollforward'][0];

        $this->assertSame(__('Paid in Full'), $row['payment_status']);
        $this->assertSame(0.0, $row['outstanding']);
    }

    public function test_a_payment_larger_than_the_balance_still_reads_as_paid_in_full_with_no_negative_outstanding(): void
    {
        $party = $this->employee();
        $this->service->generate(2023);

        $closingBalance = (float) $this->service->forYear(2023)['rollforward'][0]['closing_balance'];
        $party->employeeProfile->forceFill(['eosg_paid_amount' => $closingBalance + 500])->save();

        $row = $this->service->forYear(2023)['rollforward'][0];

        $this->assertSame(__('Paid in Full'), $row['payment_status']);
        $this->assertSame(0.0, $row['outstanding']);
    }
}
