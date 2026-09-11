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
        $december = $this->generateMonth('2025-12');
        $january = $this->generateMonth('2026-01');

        $decemberAccrual = (float) $december->payslips()->first()->eosg_accrued;
        $januaryAccrual = (float) $january->payslips()->first()->eosg_accrued;

        $this->service->generate(2025);
        $this->service->generate(2026);

        // Each year sees only the one month accrued inside it, not both.
        $voucher2025 = $this->service->forYear(2025);
        $voucher2026 = $this->service->forYear(2026);

        $this->assertSame($decemberAccrual, $voucher2025['total_debit']);
        $this->assertSame($januaryAccrual, $voucher2026['total_debit']);
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
        $expected = $gratuity->monthlyAccrual(
            $gratuity->serviceDays(Carbon::parse('2020-01-01'), Carbon::parse('2023-01-01')),
            $gratuity->serviceDays(Carbon::parse('2020-01-01'), Carbon::parse('2023-12-31')),
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
        // Has an actual payslip for 2026 — must use that, not the synthetic
        // year-end estimate.
        $withPayslip = $this->employee();
        $this->generateMonth('2026-01');
        $realAccrual = (float) PayrollRun::where('period', '2026-01')->first()->payslips()->first()->eosg_accrued;

        // Joined the same day, same salary, but never run through payroll —
        // falls back to the synthetic figure.
        $withoutPayslip = $this->employee();

        $voucher = $this->service->forYear($this->service->generate(2026)->year);

        $this->assertSame(2, $voucher['employee_count']);

        $amountsByName = array_combine(
            array_column($voucher['debits'], 'detail'),
            array_column($voucher['debits'], 'amount'),
        );

        $this->assertSame($realAccrual, $amountsByName[$withPayslip->name]);
        $this->assertArrayHasKey($withoutPayslip->name, $amountsByName);
    }
}
