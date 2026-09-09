<?php

namespace Tests\Feature;

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Services\IncentiveCalculatorService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Incentives reaching the payslip.
 *
 * A calculation belongs to the payroll month its period ENDS in — a cycle
 * running 26 June to 25 August is August's incentive, because that is when it
 * closed and when the office pays it. Keying off the start date would file it
 * under June, two months before the figure could be known.
 *
 * Only finalised calculations are imported, for the same reason payslips
 * snapshot everything else: a draft is recalculated whenever anybody presses a
 * button, and Finance approves a number, not a formula.
 */
class PayrollIncentiveImportTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payroll = app(PayrollService::class);
    }

    private function employee(): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create(['party_id' => $party->id, 'date_of_joining' => '2020-01-01']);

        EmployeeSalaryComponent::create([
            'party_id' => $party->id,
            'component' => SalaryComponent::BASIC->value,
            'amount' => 6000,
            'effective_from' => '2020-01-01',
        ]);

        return $party->fresh();
    }

    /**
     * A calculation with one assistant line worth $amount for $party.
     */
    private function calculation(
        Party $party,
        float $amount,
        string $status = 'finalized',
        string $periodEnd = '2026-08-25',
        float $fixedDeduction = 0,
    ): IncentiveCalculation {
        $calculation = IncentiveCalculation::create([
            'name' => 'Cycle to '.$periodEnd,
            'period_start' => '2026-06-26',
            'period_end' => $periodEnd,
            'status' => $status,
        ]);

        $line = IncentiveLine::create([
            'incentive_calculation_id' => $calculation->id,
            'matter_id' => Matter::factory()->create()->id,
        ]);

        IncentiveAssistantLine::create([
            'incentive_line_id' => $line->id,
            'party_id' => $party->id,
            'share_amount' => $amount,
            'total_amount' => $amount,
        ]);

        if ($fixedDeduction > 0) {
            IncentiveAssistantExtra::create([
                'incentive_calculation_id' => $calculation->id,
                'party_id' => $party->id,
                'completed_matter_count' => 1,
                'fixed_deduction' => $fixedDeduction,
            ]);
        }

        return $calculation->fresh();
    }

    private function payrollRun(string $period = '2026-08'): PayrollRun
    {
        return PayrollRun::create(['period' => $period, 'status' => PayrollRunStatus::DRAFT]);
    }

    public function test_a_finalised_cycle_ending_in_the_month_reaches_the_payslip(): void
    {
        $party = $this->employee();
        $this->calculation($party, 1500);

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        // 6,000 basic plus a 1,500 incentive earned over a cycle that closed on
        // the 25th of this month.
        $this->assertSame('1500.00', $payslip->incentive_amount);
        $this->assertSame('7500.00', $payslip->gross);
        $this->assertSame('7500.00', $payslip->net_pay);
        $this->assertFalse($payslip->incentive_overridden);
    }

    public function test_the_incentive_appears_as_its_own_payslip_line(): void
    {
        $party = $this->employee();
        $this->calculation($party, 1500);

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        // Its own line, and its own expense account — otherwise the journal
        // voucher would bury incentives inside basic salary.
        $line = $payslip->lines()->where('gl_account', PayrollService::GL_INCENTIVE)->sole();

        $this->assertSame('1500.00', $line->amount);
    }

    public function test_a_draft_cycle_pays_nothing_and_is_reported(): void
    {
        $party = $this->employee();
        $calculation = $this->calculation($party, 1500, status: 'draft');

        $run = $this->payrollRun('2026-08');
        $payslip = $this->payroll->generate($run)->first();

        $this->assertSame('0.00', $payslip->incentive_amount);

        // Silence here would be indistinguishable from a month nobody earned
        // anything in, so the run names the draft that is holding it back.
        $pending = $this->payroll->pendingIncentiveCalculations($run);

        $this->assertCount(1, $pending);
        $this->assertSame($calculation->id, $pending->first()->id);
    }

    public function test_a_cycle_closing_in_another_month_is_not_imported(): void
    {
        $party = $this->employee();
        $this->calculation($party, 1500, periodEnd: '2026-07-25');

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        $this->assertSame('0.00', $payslip->incentive_amount);
        $this->assertCount(0, $this->payroll->pendingIncentiveCalculations($this->payrollRun('2026-09')));
    }

    public function test_the_month_a_cycle_starts_in_does_not_claim_it(): void
    {
        $party = $this->employee();

        // 26 June to 25 August. June must not pay it — in June the figure does
        // not exist yet.
        $this->calculation($party, 1500);

        $payslip = $this->payroll->generate($this->payrollRun('2026-06'))->first();

        $this->assertSame('0.00', $payslip->incentive_amount);
    }

    public function test_two_cycles_closing_in_one_month_are_added_together(): void
    {
        $party = $this->employee();

        // A cycle plus a correction run: both are owed.
        $this->calculation($party, 1500, periodEnd: '2026-08-25');
        $this->calculation($party, 400, periodEnd: '2026-08-31');

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        $this->assertSame('1900.00', $payslip->incentive_amount);
    }

    public function test_a_fixed_deduction_reduces_the_imported_figure(): void
    {
        $party = $this->employee();
        $this->calculation($party, 1500, fixedDeduction: 200);

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        $this->assertSame('1300.00', $payslip->incentive_amount);
    }

    public function test_a_deduction_larger_than_the_earnings_pays_zero_not_a_negative(): void
    {
        $party = $this->employee();
        $this->calculation($party, 500, fixedDeduction: 900);

        $payslip = $this->payroll->generate($this->payrollRun('2026-08'))->first();

        // An incentive cannot be negative; a shortfall is not a salary deduction.
        $this->assertSame('0.00', $payslip->incentive_amount);
        $this->assertSame('6000.00', $payslip->net_pay);
    }

    public function test_an_employee_with_no_incentive_lines_gets_nothing(): void
    {
        $earner = $this->employee();
        $other = $this->employee();

        $this->calculation($earner, 1500);

        $payslips = $this->payroll->generate($this->payrollRun('2026-08'))->keyBy('party_id');

        $this->assertSame('1500.00', $payslips[$earner->id]->incentive_amount);
        $this->assertSame('0.00', $payslips[$other->id]->incentive_amount);
    }

    public function test_an_overridden_incentive_survives_regeneration(): void
    {
        $party = $this->employee();
        $this->calculation($party, 1500);

        $run = $this->payrollRun('2026-08');

        $this->payroll->generate($run)->first()
            ->forceFill(['incentive_amount' => 1200, 'incentive_overridden' => true])->save();

        $payslip = $this->payroll->generate($run)->first();

        $this->assertSame('1200.00', $payslip->incentive_amount);
        $this->assertTrue($payslip->incentive_overridden);
    }

    public function test_the_payroll_figure_matches_the_statement_the_assistant_is_shown(): void
    {
        $party = $this->employee();
        $calculation = $this->calculation($party, 1500, fixedDeduction: 200);

        $calculator = app(IncentiveCalculatorService::class);

        $fromSummary = $calculator->getAssistantSummary($calculation)
            ->firstWhere('party.id', $party->id)['total'];

        // Two code paths, one number. The lean aggregate payroll uses must agree
        // with the per-matter summary the assistant reads on their statement —
        // a payroll figure that quietly disagrees is worse than either being
        // wrong alone.
        $this->assertEqualsWithDelta(
            $fromSummary,
            $calculator->payableByParty($calculation)[$party->id],
            0.005,
        );
    }
}
