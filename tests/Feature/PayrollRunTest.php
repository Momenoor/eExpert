<?php

namespace Tests\Feature;

use App\Enums\LeaveType;
use App\Enums\LoanKind;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeLoan;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\LoanScheduleService;
use App\Services\PayrollJournalVoucherService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * The monthly payroll engine, end to end.
 *
 * The scenario throughout is one employee on 6,000 basic with 4,000 of
 * allowances, because that split is where the mistakes live: gratuity and unpaid
 * days are computed on the 6,000, while gross pay and the bank transfer are
 * computed on the 10,000. A single figure for "salary" would pass most tests and
 * be wrong in production.
 */
class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payroll = app(PayrollService::class);
    }

    private function employee(array $profile = []): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create([
            'party_id' => $party->id,
            'date_of_joining' => '2020-01-01',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            ...$profile,
        ]);

        foreach ([
            SalaryComponent::BASIC->value => 6000,
            SalaryComponent::HOUSING->value => 3000,
            SalaryComponent::TRANSPORT->value => 1000,
        ] as $component => $amount) {
            EmployeeSalaryComponent::create([
                'party_id' => $party->id,
                'component' => $component,
                'amount' => $amount,
                'effective_from' => '2020-01-01',
            ]);
        }

        return $party->fresh();
    }

    private function payrollRun(string $period = '2026-06'): PayrollRun
    {
        return PayrollRun::create(['period' => $period, 'status' => PayrollRunStatus::DRAFT]);
    }

    public function test_it_pays_gross_when_nothing_is_deducted(): void
    {
        $this->employee();

        $payslips = $this->payroll->generate($this->payrollRun());

        $this->assertCount(1, $payslips);

        $payslip = $payslips->first();

        $this->assertSame('6000.00', $payslip->basic_snapshot);
        $this->assertSame('4000.00', $payslip->allowances_snapshot);
        $this->assertSame('10000.00', $payslip->gross);
        $this->assertSame('10000.00', $payslip->net_pay);
        $this->assertFalse($payslip->needs_review);
    }

    public function test_only_parties_holding_the_employee_role_are_paid(): void
    {
        $this->employee();

        // An assistant expert with a profile and a salary is still not an
        // employee: the payroll module must see only the employee role.
        $assistant = Party::factory()->assistant()->create();
        EmployeeProfile::create(['party_id' => $assistant->id, 'date_of_joining' => '2020-01-01']);

        $payslips = $this->payroll->generate($this->payrollRun());

        $this->assertCount(1, $payslips);
        $this->assertNotSame($assistant->id, $payslips->first()->party_id);
    }

    public function test_an_employee_without_a_profile_is_not_paid(): void
    {
        Party::factory()->employee()->create();

        $this->assertCount(0, $this->payroll->generate($this->payrollRun()));
    }

    public function test_unpaid_leave_is_deducted_at_basic_over_thirty(): void
    {
        $party = $this->employee();

        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-14',
            'leave_type' => LeaveType::UNPAID,
            'pay_factor' => 0,
        ]);

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        // Five days at 6,000/30 = 200 a day. Had the rate been taken off gross
        // pay it would be 333.33 a day, and the employee would lose 666 AED too
        // much on a single week's unpaid leave.
        $this->assertSame('5.0', $payslip->unpaid_days);
        $this->assertSame('1000.00', $payslip->unpaid_deduction);
        $this->assertSame('9000.00', $payslip->net_pay);
    }

    public function test_half_pay_sick_leave_withholds_half_a_day_per_day(): void
    {
        $party = $this->employee();

        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-04',
            'leave_type' => LeaveType::SICK_HALF,
            'pay_factor' => 0.5,
        ]);

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        $this->assertSame('2.0', $payslip->unpaid_days);
        $this->assertSame('400.00', $payslip->unpaid_deduction);
    }

    public function test_leave_recorded_before_this_module_existed_costs_nothing(): void
    {
        $party = $this->employee();

        // No leave_type, no pay_factor: a row hand-entered to explain an absence,
        // not to dock anyone. Reading it as unpaid would invent a deduction out
        // of history — and there are such rows in production already.
        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-14',
            'reason' => 'Annual leave',
        ]);

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        $this->assertSame('0.0', $payslip->unpaid_days);
        $this->assertSame('10000.00', $payslip->net_pay);
    }

    public function test_leave_straddling_the_month_end_is_counted_only_for_its_days_inside(): void
    {
        $party = $this->employee();

        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => '2026-06-28',
            'end_date' => '2026-07-05',
            'leave_type' => LeaveType::UNPAID,
            'pay_factor' => 0,
        ]);

        $payslip = $this->payroll->generate($this->payrollRun('2026-06'))->first();

        // 28, 29, 30 June — July's five days belong to July's payslip.
        $this->assertSame('3.0', $payslip->unpaid_days);
    }

    public function test_a_loan_instalment_is_deducted_and_marked_as_recovered(): void
    {
        $party = $this->employee();

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 10000,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $payslip = $this->payroll->generate($this->payrollRun('2026-06'))->first();

        // Month one carries the rounding remainder: 10,000 − 1,650 × 5.
        $this->assertSame('1750.00', $payslip->loan_deduction);
        $this->assertSame('8250.00', $payslip->net_pay);

        $this->assertSame(
            $payslip->id,
            $loan->installments()->where('seq', 1)->value('payslip_id'),
        );
        $this->assertEqualsWithDelta(8250.0, $loan->fresh()->outstanding(), 0.005);
    }

    public function test_regenerating_a_draft_releases_the_instalments_it_had_taken(): void
    {
        $party = $this->employee();

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 10000,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $run = $this->payrollRun('2026-06');

        $this->payroll->generate($run);
        $second = $this->payroll->generate($run)->first();

        // The second pass must deduct the same instalment, not skip it as
        // already recovered and not double-count it either.
        $this->assertSame('1750.00', $second->loan_deduction);
        $this->assertSame(1, $loan->installments()->whereNotNull('payslip_id')->count());
        $this->assertSame(1, Payslip::count());
    }

    public function test_regenerating_preserves_hand_entered_figures(): void
    {
        $this->employee();

        $run = $this->payrollRun();

        // manual_deduction has no source to be re-derived from, and an
        // incentive marked as overridden is a figure somebody chose on purpose.
        $this->payroll->generate($run)->first()->forceFill([
            'manual_deduction' => 500,
            'incentive_amount' => 250,
            'incentive_overridden' => true,
        ])->save();

        $payslip = $this->payroll->generate($run)->first();

        $this->assertSame('500.00', $payslip->manual_deduction);
        $this->assertSame('250.00', $payslip->incentive_amount);
        $this->assertTrue($payslip->incentive_overridden);
        $this->assertSame('10250.00', $payslip->gross);
        $this->assertSame('9750.00', $payslip->net_pay);
    }

    public function test_an_unflagged_incentive_is_re_derived_on_regeneration(): void
    {
        $this->employee();

        $run = $this->payrollRun();

        // Not marked as overridden, so it came from an import — and an import
        // that survived a regeneration would keep paying an incentive after the
        // calculation behind it was corrected or withdrawn.
        $this->payroll->generate($run)->first()
            ->forceFill(['incentive_amount' => 250])->save();

        $payslip = $this->payroll->generate($run)->first();

        $this->assertSame('0.00', $payslip->incentive_amount);
    }

    public function test_deductions_beyond_gross_are_flagged_rather_than_clamped(): void
    {
        $this->employee();

        $run = $this->payrollRun();

        $this->payroll->generate($run)->first()
            ->forceFill(['manual_deduction' => 12000])->save();

        $payslip = $this->payroll->generate($run)->first();

        $this->assertTrue($payslip->needs_review);
        $this->assertSame('-2000.00', $payslip->net_pay);
    }

    public function test_gratuity_accrues_on_basic_and_is_not_deducted_from_the_employee(): void
    {
        $this->employee();

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        // Six years in, a month is worth roughly 30/12 days of the 200 daily
        // basic rate. What matters here is that it is charged to the employer:
        // net pay is untouched by it.
        $this->assertGreaterThan(0, (float) $payslip->eosg_accrued);
        $this->assertSame('10000.00', $payslip->net_pay);
    }

    public function test_gratuity_is_not_posted_to_the_monthly_salaries_voucher(): void
    {
        $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        // Still accrued and stored on the payslip (asserted above) — it just
        // does not reach the monthly voucher any more. It is posted once a
        // year instead, by EndOfServiceGratuityClosingVoucherService.
        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);

        $accounts = [...array_column($voucher['debits'], 'account'), ...array_column($voucher['credits'], 'account')];

        $this->assertNotContains(PayrollService::GL_EOSG_EXPENSE, $accounts);
        $this->assertNotContains(PayrollJournalVoucherService::GL_EOSG_PROVISION, $accounts);
    }

    public function test_an_employee_not_applicable_for_eosg_accrues_nothing(): void
    {
        $party = $this->employee();
        $party->employeeProfile->forceFill(['is_eosg_applicable' => false])->save();

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        $this->assertSame('0.00', $payslip->eosg_accrued);
    }

    public function test_an_employee_who_left_before_the_period_is_not_paid(): void
    {
        $this->employee(['date_of_leaving' => '2026-01-31']);

        $this->assertCount(0, $this->payroll->generate($this->payrollRun('2026-06')));
    }

    public function test_an_employee_hired_after_the_period_is_not_paid(): void
    {
        $this->employee(['date_of_joining' => '2026-09-01']);

        $this->assertCount(0, $this->payroll->generate($this->payrollRun('2026-06')));
    }

    public function test_a_run_past_draft_cannot_be_regenerated(): void
    {
        $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $run->forceFill(['status' => PayrollRunStatus::HR_REVIEW])->save();

        $this->expectException(RuntimeException::class);

        $this->payroll->generate($run->fresh());
    }

    public function test_a_loan_is_locked_for_editing_once_an_instalment_is_taken(): void
    {
        $party = $this->employee();

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 10000,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $this->assertTrue($loan->isEditable());

        $this->payroll->generate($this->payrollRun('2026-06'));

        // The first instalment is now part of a payslip. Changing the amount or
        // the term would rewrite a schedule that has already been partly
        // recovered, so the whole advance closes to editing.
        $this->assertFalse($loan->fresh()->isEditable());
        $this->assertTrue($loan->fresh()->hasDeductedInstallments());
    }

    public function test_the_schedule_cannot_be_rebuilt_once_an_instalment_is_taken(): void
    {
        $party = $this->employee();

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::LOAN,
            'principal' => 10000,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);
        $this->payroll->generate($this->payrollRun('2026-06'));

        $this->expectException(InvalidArgumentException::class);

        app(LoanScheduleService::class)->generateFor($loan->fresh());
    }

    public function test_the_voucher_itemises_loan_clearing_by_employee(): void
    {
        $first = $this->employee();
        $second = $this->employee();

        foreach ([$first, $second] as $party) {
            $loan = EmployeeLoan::create([
                'party_id' => $party->id,
                'kind' => LoanKind::LOAN,
                'principal' => 1200,
                'months' => 6,
                'starts_on' => '2026-06-01',
            ]);

            app(LoanScheduleService::class)->generateFor($loan);
        }

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);

        $loanCredits = array_values(array_filter(
            $voucher['credits'],
            fn (array $line): bool => $line['account'] === 'Staff Loans Receivable',
        ));

        // One line each, named. A single lump credit balances just as well but
        // cannot be reconciled against a loan ledger that is kept per person.
        $this->assertCount(2, $loanCredits);
        $this->assertEqualsCanonicalizing(
            [$first->name, $second->name],
            array_column($loanCredits, 'detail'),
        );

        $this->assertTrue($voucher['balanced']);
    }

    public function test_everything_but_loan_clearing_stays_summarised(): void
    {
        $this->employee();
        $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);

        $salaries = array_values(array_filter(
            $voucher['debits'],
            fn (array $line): bool => $line['account'] === 'Salaries Expense',
        ));

        // Two employees at 10,000 (basic + allowances) each, one merged
        // Salaries Expense line — the expense side reconciles against the
        // payroll register, not against individuals.
        $this->assertCount(1, $salaries);
        $this->assertNull($salaries[0]['detail']);
        $this->assertSame(20000.0, $salaries[0]['amount']);
    }

    public function test_a_payslip_carries_every_instalment_behind_its_loan_figure(): void
    {
        $party = $this->employee();

        // Two concurrent advances: a staff loan and a petty cash float.
        foreach ([[LoanKind::LOAN, 1200], [LoanKind::PETTY_CASH, 600]] as [$kind, $principal]) {
            $loan = EmployeeLoan::create([
                'party_id' => $party->id,
                'kind' => $kind,
                'principal' => $principal,
                'months' => 6,
                'starts_on' => '2026-06-01',
            ]);

            app(LoanScheduleService::class)->generateFor($loan);
        }

        $payslip = $this->payroll->generate($this->payrollRun())->first();

        // One figure on the payslip, two instalments behind it — which is why
        // the column opens a breakdown rather than just showing a total.
        $this->assertSame('300.00', $payslip->loan_deduction);
        $this->assertSame(2, $payslip->installments()->count());
    }

    public function test_the_journal_voucher_balances(): void
    {
        $party = $this->employee();

        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-14',
            'leave_type' => LeaveType::UNPAID,
            'pay_factor' => 0,
        ]);

        $loan = EmployeeLoan::create([
            'party_id' => $party->id,
            'kind' => LoanKind::PETTY_CASH,
            'principal' => 1200,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);

        $this->assertTrue($voucher['balanced'], 'Journal voucher does not balance.');
        $this->assertSame($voucher['total_debit'], $voucher['total_credit']);
        $this->assertSame(1, $voucher['employee_count']);

        $accounts = array_column($voucher['debits'], 'account');
        $this->assertContains('Salaries Expense', $accounts);

        $credits = array_column($voucher['credits'], 'account');
        $this->assertContains('Net Salary Payable', $credits);
        $this->assertContains('Petty Cash Advances', $credits);
        $this->assertContains('Unpaid Leave Recovery', $credits);
    }

    public function test_the_voucher_debits_gross_not_net(): void
    {
        $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);

        $salaryAndAllowances = array_sum(array_map(
            fn (array $line): float => str_contains($line['account'], 'EOSG') ? 0.0 : $line['amount'],
            $voucher['debits'],
        ));

        // The expense side must reconcile against the payroll register, which is
        // gross. Posting net would leave every deduction account unexplained.
        $this->assertSame(10000.0, $salaryAndAllowances);
    }
}
