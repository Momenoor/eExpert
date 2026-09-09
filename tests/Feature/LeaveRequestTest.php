<?php

namespace Tests\Feature;

use App\Enums\LeaveType;
use App\Enums\PayrollRunStatus;
use App\Enums\RequestStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestPeriod;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\LeaveEntitlementService;
use App\Services\LeaveRequestService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * The leave request workflow and its two consumers.
 *
 * The load-bearing assertion in this file is that a PENDING request changes
 * nothing. `party_leaves` is read by the incentive calculator to prorate monthly
 * quotas and by payroll to withhold pay; if a request touched that ledger before
 * approval, submitting one would dock your own salary.
 */
class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private LeaveRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeaveRequestService::class);
    }

    private function employee(string $joinedOn = '2020-01-01'): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create(['party_id' => $party->id, 'date_of_joining' => $joinedOn]);

        EmployeeSalaryComponent::create([
            'party_id' => $party->id,
            'component' => SalaryComponent::BASIC->value,
            'amount' => 6000,
            'effective_from' => $joinedOn,
        ]);

        return $party->fresh();
    }

    /**
     * What an employee actually submits: dates and a reason, nothing more.
     */
    private function request(Party $party, string $start, string $end): LeaveRequest
    {
        return LeaveRequest::create([
            'party_id' => $party->id,
            'status' => RequestStatus::PENDING,
            'start_date' => $start,
            'end_date' => $end,
            'comment' => 'Family matters',
        ])->fresh();
    }

    /**
     * The split an approver would type into the approval form.
     *
     * @param  array<int, array{0: LeaveType, 1: string, 2: string, 3: float}>  $rows
     * @return list<array{leave_type: string, start_date: string, end_date: string, day_count: float}>
     */
    private function split(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'leave_type' => $row[0]->value,
            'start_date' => $row[1],
            'end_date' => $row[2],
            'day_count' => $row[3],
        ], $rows);
    }

    public function test_a_pending_request_writes_nothing_to_the_leave_ledger(): void
    {
        $party = $this->employee();

        $this->request($party, '2026-06-10', '2026-06-14');

        $this->assertSame(0, PartyLeave::count());

        $payslip = app(PayrollService::class)
            ->generate(PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]))
            ->first();

        $this->assertSame('0.0', $payslip->unpaid_days);
    }

    public function test_approval_publishes_every_period_to_the_leave_ledger(): void
    {
        $party = $this->employee();

        // The employee asks for ten days off. The approver decides what they
        // are: three annual, two casual, five unpaid.
        $request = $this->request($party, '2026-06-01', '2026-06-10');

        $this->assertSame(0, $request->periods()->count());

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-03', 3],
            [LeaveType::CASUAL, '2026-06-04', '2026-06-05', 2],
            [LeaveType::UNPAID, '2026-06-06', '2026-06-10', 5],
        ]));

        $this->assertSame(3, PartyLeave::count());
        $this->assertSame(RequestStatus::APPROVED, $request->fresh()->status);

        $unpaid = PartyLeave::where('leave_type', LeaveType::UNPAID->value)->sole();
        $this->assertSame('0.00', $unpaid->pay_factor);
        $this->assertSame($party->id, $unpaid->party_id);
    }

    public function test_only_the_unpaid_portion_of_a_split_reaches_payroll(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-10');

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-03', 3],
            [LeaveType::CASUAL, '2026-06-04', '2026-06-05', 2],
            [LeaveType::UNPAID, '2026-06-06', '2026-06-10', 5],
        ]));

        $payslip = app(PayrollService::class)
            ->generate(PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]))
            ->first();

        // Ten days away, five days docked: 5 × (6,000/30) = 1,000.
        $this->assertSame('5.0', $payslip->unpaid_days);
        $this->assertSame('1000.00', $payslip->unpaid_deduction);
    }

    public function test_the_pay_factor_is_derived_from_the_type_not_typed_in(): void
    {
        $party = $this->employee();

        $period = LeaveRequestPeriod::create([
            'leave_request_id' => $this->request($party, '2026-06-01', '2026-06-02')->id,
            'leave_type' => LeaveType::SICK_HALF,
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-02',
            'day_count' => 1,
            // An approver trying to make an absence paid must change the leave
            // type, where it is visible, not the multiplier.
            'pay_factor' => 1,
        ]);

        $this->assertSame('0.50', $period->fresh()->pay_factor);
    }

    public function test_approving_annual_leave_draws_down_the_annual_balance(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-05', 5],
        ]));

        $entitlement = app(LeaveEntitlementService::class)
            ->forDate($party, Carbon::parse('2026-06-01'));

        $this->assertSame('5.0', $entitlement->annual_taken_days);
        $this->assertSame(25.0, $entitlement->annualRemaining());
    }

    public function test_unpaid_leave_draws_on_no_balance(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::UNPAID, '2026-06-01', '2026-06-05', 5],
        ]));

        $entitlement = app(LeaveEntitlementService::class)
            ->forDate($party, Carbon::parse('2026-06-01'));

        $this->assertSame('0.0', $entitlement->annual_taken_days);
    }

    public function test_a_rejection_must_state_a_reason(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $this->expectException(RuntimeException::class);

        $this->service->reject($request, User::factory()->create(), '   ');
    }

    public function test_a_rejection_records_the_reason_and_leaves_the_ledger_empty(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $this->service->reject($request, User::factory()->create(), 'Court hearing that week.');

        $request = $request->fresh();

        $this->assertSame(RequestStatus::REJECTED, $request->status);
        $this->assertSame('Court hearing that week.', $request->approved_comment);
        $this->assertSame(0, PartyLeave::count());
    }

    public function test_a_request_can_only_be_decided_once(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');
        $approver = User::factory()->create();
        $split = $this->split([[LeaveType::ANNUAL, '2026-06-01', '2026-06-05', 5]]);

        $this->service->approve($request, $approver, $split);

        $this->expectException(RuntimeException::class);

        $this->service->approve($request->fresh(), $approver, $split);
    }

    public function test_approving_without_a_split_is_refused(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        // Approving with no periods would set the status to approved while
        // writing nothing to the leave ledger: an absence that reads as granted
        // and costs nothing, which is the worst of both.
        $this->expectException(RuntimeException::class);

        $this->service->approve($request, User::factory()->create(), []);
    }

    public function test_a_split_reaching_outside_the_requested_dates_is_refused(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $this->expectException(RuntimeException::class);

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-30', 30],
        ]));
    }

    public function test_a_split_totalling_more_than_the_days_requested_is_refused(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        // Five days asked for, eight booked. The dates are all inside the
        // window, so only the total catches this.
        $this->expectException(RuntimeException::class);

        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-03', 3],
            [LeaveType::UNPAID, '2026-06-04', '2026-06-05', 5],
        ]));
    }

    public function test_a_split_may_grant_fewer_days_than_were_asked_for(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        // Granting three of the five days requested is a normal outcome, not an
        // error: the approver is allowed to give less than was asked.
        $this->service->approve($request, User::factory()->create(), $this->split([
            [LeaveType::ANNUAL, '2026-06-01', '2026-06-03', 3],
        ]));

        $this->assertSame(3.0, $request->fresh()->totalDays());
        $this->assertSame(5.0, $request->fresh()->requestedDays());
    }

    public function test_the_suggested_split_covers_the_whole_request_as_annual_leave(): void
    {
        $party = $this->employee();

        $request = $this->request($party, '2026-06-01', '2026-06-05');

        $suggestion = $this->service->suggestSplit($request);

        $this->assertCount(1, $suggestion);
        $this->assertSame(LeaveType::ANNUAL->value, $suggestion[0]['leave_type']);
        $this->assertSame(5.0, $suggestion[0]['day_count']);
    }

    public function test_the_service_year_runs_from_the_joining_anniversary(): void
    {
        $entitlements = app(LeaveEntitlementService::class);

        $joined = Carbon::parse('2020-09-15');

        // A sickness in August belongs to the year that began the previous
        // September — not to the calendar year, which would hand out a second
        // full-pay allowance every January.
        $this->assertSame(
            '2025-09-15',
            $entitlements->serviceYearStart($joined, Carbon::parse('2026-08-31'))->toDateString(),
        );

        $this->assertSame(
            '2026-09-15',
            $entitlements->serviceYearStart($joined, Carbon::parse('2026-09-15'))->toDateString(),
        );
    }

    public function test_annual_leave_accrues_at_two_days_a_month_in_the_first_year(): void
    {
        $entitlements = app(LeaveEntitlementService::class);

        $joined = Carbon::parse('2026-01-01');

        $this->assertSame(0.0, $entitlements->annualEntitlement($joined, Carbon::parse('2026-04-01')));
        $this->assertSame(14.0, $entitlements->annualEntitlement($joined, Carbon::parse('2026-08-01')));
        $this->assertSame(30.0, $entitlements->annualEntitlement($joined, Carbon::parse('2027-01-01')));
    }

    public function test_sick_days_fall_through_the_statutory_bands_in_order(): void
    {
        $party = $this->employee();

        $entitlements = app(LeaveEntitlementService::class);
        $entitlement = $entitlements->forDate($party, Carbon::parse('2026-06-01'));

        // A first illness of twenty days: fifteen at full pay, five at half.
        $split = $entitlements->splitSickDays($entitlement, 20);

        $this->assertSame(
            [[LeaveType::SICK_FULL, 15.0], [LeaveType::SICK_HALF, 5.0]],
            array_map(fn (array $part): array => [$part['leave_type'], $part['day_count']], $split),
        );
    }

    public function test_sick_days_past_ninety_in_a_service_year_become_ordinary_unpaid_leave(): void
    {
        $party = $this->employee();

        $entitlements = app(LeaveEntitlementService::class);
        $entitlement = $entitlements->forDate($party, Carbon::parse('2026-06-01'));

        $entitlement->forceFill([
            'sick_full_taken' => 15,
            'sick_half_taken' => 30,
            'sick_unpaid_taken' => 45,
        ])->save();

        $split = $entitlements->splitSickDays($entitlement, 4);

        $this->assertSame([[LeaveType::UNPAID, 4.0]],
            array_map(fn (array $part): array => [$part['leave_type'], $part['day_count']], $split));
    }
}
