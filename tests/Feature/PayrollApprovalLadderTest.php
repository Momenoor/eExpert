<?php

namespace Tests\Feature;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeLoan;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\EosgAccrual;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\LoanScheduleService;
use App\Services\PayrollRunService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Draft → HR Review → Finance Approval → Approved → Disbursed.
 *
 * The ladder exists to record who asserted what, so the assertions here are as
 * much about the audit trail as the status: an approval that survives a
 * recalculation would put Finance's name against figures they never saw.
 */
class PayrollApprovalLadderTest extends TestCase
{
    use RefreshDatabase;

    private PayrollRunService $ladder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ladder = app(PayrollRunService::class);
    }

    private function employee(): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create([
            'party_id' => $party->id,
            'date_of_joining' => '2020-01-01',
        ]);

        EmployeeSalaryComponent::create([
            'party_id' => $party->id,
            'component' => SalaryComponent::BASIC->value,
            'amount' => 6000,
            'effective_from' => '2020-01-01',
        ]);

        return $party->fresh();
    }

    private function generatedRun(): PayrollRun
    {
        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);

        app(PayrollService::class)->generate($run);

        return $run->fresh();
    }

    public function test_an_empty_draft_cannot_be_sent_for_review(): void
    {
        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);

        $this->expectException(RuntimeException::class);

        $this->ladder->submitForReview($run);
    }

    public function test_the_ladder_runs_in_order_and_records_each_approver(): void
    {
        $this->employee();
        $run = $this->generatedRun();

        $hr = User::factory()->create();
        $finance = User::factory()->create();

        $this->ladder->submitForReview($run);
        $this->assertSame(PayrollRunStatus::HR_REVIEW, $run->fresh()->status);

        $this->ladder->hrApprove($run, $hr);
        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::FINANCE_APPROVAL, $run->status);
        $this->assertSame($hr->id, $run->hr_approved_by);
        $this->assertNotNull($run->hr_approved_at);

        $this->ladder->financeApprove($run, $finance);
        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::APPROVED, $run->status);
        $this->assertSame($finance->id, $run->finance_approved_by);

        // Two different people, recorded separately. A single Approve field
        // would have lost the fact that these are distinct assertions.
        $this->assertNotSame($run->hr_approved_by, $run->finance_approved_by);
    }

    public function test_a_rung_cannot_be_skipped(): void
    {
        $this->employee();
        $run = $this->generatedRun();

        $this->expectException(RuntimeException::class);

        $this->ladder->financeApprove($run, User::factory()->create());
    }

    public function test_hr_cannot_approve_while_a_payslip_is_flagged_for_review(): void
    {
        $this->employee();
        $run = $this->generatedRun();

        // A negative net: the deductions are wrong, or a loan is being recovered
        // too fast. Passing this to Finance would produce a bank file asking the
        // employee for money back.
        $run->payslips()->first()->forceFill(['needs_review' => true])->save();

        $this->ladder->submitForReview($run);

        $this->expectException(RuntimeException::class);

        $this->ladder->hrApprove($run->fresh(), User::factory()->create());
    }

    public function test_returning_to_draft_clears_the_approvals(): void
    {
        $this->employee();
        $run = $this->generatedRun();

        $this->ladder->submitForReview($run);
        $this->ladder->hrApprove($run, User::factory()->create());

        $this->ladder->returnToDraft($run->fresh());

        $run = $run->fresh();

        $this->assertSame(PayrollRunStatus::DRAFT, $run->status);
        $this->assertNull($run->hr_approved_by);
        $this->assertNull($run->hr_approved_at);
    }

    public function test_a_disbursed_run_cannot_be_reopened(): void
    {
        $this->employee();
        $run = $this->disbursedRun();

        $this->expectException(RuntimeException::class);

        $this->ladder->returnToDraft($run);
    }

    public function test_disbursing_books_the_gratuity_accrual_for_the_period(): void
    {
        $party = $this->employee();

        $this->disbursedRun();

        $accrual = EosgAccrual::where('party_id', $party->id)->where('period', '2026-06')->sole();

        // Six and a half years of service on a 6,000 basic: five years at 21 days
        // plus the rest at 30, all capped at two years' pay. What matters here is
        // that the liability is recorded at all — it is the office's largest
        // unbooked obligation and nothing tracked it before.
        $this->assertGreaterThan(0, (float) $accrual->cumulative_liability);
        $this->assertGreaterThan(0, $accrual->service_days);
        $this->assertSame('6000.00', $accrual->basic_snapshot);
    }

    public function test_disbursing_closes_a_loan_whose_last_instalment_was_taken(): void
    {
        $party = $this->employee();

        // One month, one instalment: the loan is fully recovered by this run.
        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 500,
            'months' => 1,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $this->disbursedRun();

        $this->assertSame(LoanStatus::SETTLED, $loan->fresh()->status);
    }

    public function test_a_partly_recovered_loan_stays_active(): void
    {
        $party = $this->employee();

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 3000,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $this->disbursedRun();

        $this->assertSame(LoanStatus::ACTIVE, $loan->fresh()->status);
    }

    public function test_totals_reconcile_gross_less_deductions_to_net(): void
    {
        $this->employee();
        $run = $this->generatedRun();

        $totals = $this->ladder->totals($run);

        $this->assertSame(1, $totals['employees']);
        $this->assertEqualsWithDelta(
            $totals['gross'] - $totals['deductions'],
            $totals['net'],
            0.005,
        );
    }

    private function disbursedRun(): PayrollRun
    {
        $run = $this->generatedRun();

        $this->ladder->submitForReview($run);
        $this->ladder->hrApprove($run, User::factory()->create());
        $this->ladder->financeApprove($run->fresh(), User::factory()->create());
        $this->ladder->disburse($run->fresh());

        return $run->fresh();
    }
}
