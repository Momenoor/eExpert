<?php

namespace Tests\Feature;

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Services\EndOfServiceGratuityClosingVoucherService;
use App\Services\PayrollJournalVoucherService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The annual EOSG closing voucher: one year's worth of monthly accruals,
 * summed into a single entry, itemised per employee.
 */
class EndOfServiceGratuityClosingVoucherServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_sums_a_years_monthly_accruals_into_one_itemised_entry(): void
    {
        $party = $this->employee();

        foreach (['2026-01', '2026-02', '2026-03'] as $period) {
            $this->generateMonth($period);
        }

        $voucher = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2026);

        $this->assertSame(1, $voucher['employee_count']);
        $this->assertTrue($voucher['balanced']);
        $this->assertSame($voucher['total_debit'], $voucher['total_credit']);

        $this->assertCount(1, $voucher['debits']);
        $this->assertSame(PayrollService::GL_EOSG_EXPENSE, $voucher['debits'][0]['account']);
        $this->assertSame($party->name, $voucher['debits'][0]['detail']);
        $this->assertGreaterThan(0, $voucher['debits'][0]['amount']);

        $this->assertCount(1, $voucher['credits']);
        $this->assertSame(PayrollJournalVoucherService::GL_EOSG_PROVISION, $voucher['credits'][0]['account']);
    }

    public function test_it_excludes_an_employee_not_applicable_for_eosg(): void
    {
        $party = $this->employee(['is_eosg_applicable' => false]);
        $this->generateMonth('2026-01');

        $voucher = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2026);

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

        // Each year sees only the one month accrued inside it, not both.
        $voucher2025 = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2025);
        $voucher2026 = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2026);

        $this->assertSame($decemberAccrual, $voucher2025['total_debit']);
        $this->assertSame($januaryAccrual, $voucher2026['total_debit']);
    }

    public function test_a_year_with_nothing_accrued_is_empty_but_balanced(): void
    {
        $voucher = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2019);

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

        $voucher = app(EndOfServiceGratuityClosingVoucherService::class)->forYear(2026);

        $this->assertSame(2, $voucher['employee_count']);
        $this->assertCount(2, $voucher['debits']);
        $this->assertEqualsCanonicalizing(
            [$first->name, $second->name],
            array_column($voucher['debits'], 'detail'),
        );
    }
}
