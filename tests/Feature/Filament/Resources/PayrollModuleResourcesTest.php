<?php

namespace Tests\Feature\Filament\Resources;

use App\Enums\LeaveType;
use App\Enums\LoanKind;
use App\Enums\PayrollRunStatus;
use App\Enums\RequestStatus;
use App\Enums\SalaryComponent;
use App\Filament\Concerns\PayrollRefresh;
use App\Filament\Pages\Payroll\EndOfServiceGratuityClosingVoucher;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\EmployeeLoan;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\LoanScheduleService;
use App\Services\PayrollJournalVoucherService;
use App\Services\PayrollService;
use Database\Seeders\PayrollModulePermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The payroll and vacation screens.
 *
 * The denial tests here matter more than the render tests. Filament's
 * ->visible() only hides a button; it is ->authorize() that refuses the call.
 * An audit of this codebase found six irreversible incentive actions gated by
 * visibility alone, so these assert the server-side barrier, not the absence of
 * a button.
 */
class PayrollModuleResourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Shield's super_admin is configured with define_via_gate = false, so it
        // holds no implicit powers: the seeder that grants this module's
        // permissions on the live database is what makes the screens reachable
        // here too, which incidentally keeps it covered.
        $this->seed(PayrollModulePermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');
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

    private function pendingRequest(Party $party): LeaveRequest
    {
        return LeaveRequest::create([
            'party_id' => $party->id,
            'status' => RequestStatus::PENDING,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ])->fresh();
    }

    /**
     * An employee whose user account is linked to their own party record.
     */
    private function selfServiceUser(Party $party): User
    {
        $user = User::factory()->create();

        $party->forceFill(['user_id' => $user->id])->save();

        $role = Role::firstOrCreate(['name' => 'employee_self_service', 'guard_name' => 'web']);

        foreach (['ViewAny:LeaveRequest', 'View:LeaveRequest', 'Create:LeaveRequest'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role->givePermissionTo(['ViewAny:LeaveRequest', 'View:LeaveRequest', 'Create:LeaveRequest']);
        $user->assignRole($role);

        return $user;
    }

    public function test_the_module_pages_render(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);

        $this->get(EmployeeProfileResource::getUrl('index'))->assertSuccessful();
        $this->get(EmployeeProfileResource::getUrl('create'))->assertSuccessful();
        $this->get(LeaveRequestResource::getUrl('index'))->assertSuccessful();
        $this->get(LeaveRequestResource::getUrl('create'))->assertSuccessful();
        $this->get(EmployeeLoanResource::getUrl('index'))->assertSuccessful();
        $this->get(EmployeeLoanResource::getUrl('create'))->assertSuccessful();
        $this->get(EmployeeLoanResource::getUrl('view', ['record' => $this->loan()]))->assertSuccessful();
        $this->get(PayrollRunResource::getUrl('index'))->assertSuccessful();
        $this->get(PayrollRunResource::getUrl('view', ['record' => $run]))->assertSuccessful();
    }

    private function loan(): EmployeeLoan
    {
        $loan = EmployeeLoan::create([
            'party_id' => $this->employee()->id,
            'kind' => LoanKind::LOAN,
            'principal' => 1200,
            'months' => 6,
            'starts_on' => '2026-06-01',
        ]);

        app(LoanScheduleService::class)->generateFor($loan);

        return $loan->fresh();
    }

    public function test_a_part_recovered_loan_can_still_be_viewed_but_not_edited(): void
    {
        $loan = $this->loan();

        // Take the first instalment.
        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        $loan = $loan->fresh();
        $this->assertFalse($loan->isEditable());

        // The view page is the whole point: the schedule outlives the right to
        // change it, and before this page existed there was nowhere to read it.
        $this->get(EmployeeLoanResource::getUrl('view', ['record' => $loan]))->assertSuccessful();

        // The edit page refuses outright. EditRecord authorises on mount, so the
        // policy answers before any form is built or any save is attempted.
        $this->get(EmployeeLoanResource::getUrl('edit', ['record' => $loan]))->assertForbidden();

        $this->assertFalse($this->admin->can('update', $loan));
    }

    public function test_the_printable_journal_voucher_renders(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        $this->get(route('payroll.run.journal-voucher.print', $run))
            ->assertSuccessful()
            ->assertSee(__('Journal Voucher'))
            ->assertSee(__('Salaries Expense'))
            ->assertSee(__('Net Salary Payable'))
            ->assertSee('2026-06');
    }

    public function test_the_printable_voucher_is_refused_without_the_permission(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        // The whole office's salary bill on one sheet. `auth` alone is not a
        // permission — the incentive print route carried exactly that, and every
        // signed-in user could read the payroll from it.
        $this->actingAs(User::factory()->create());

        $this->get(route('payroll.run.journal-voucher.print', $run))->assertForbidden();
    }

    public function test_every_payroll_screen_listens_for_the_refresh_event(): void
    {
        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);

        // Livewire only re-renders the component an action ran on. Each of these
        // displays data that a different component changes, so each has to be
        // listening or the operator reads stale figures under a success message.
        $listeners = Livewire::test(ViewPayrollRun::class, ['record' => $run->getKey()])
            ->effects['listeners'] ?? [];

        $this->assertContains(PayrollRefresh::EVENT, $listeners);
    }

    public function test_approving_from_the_queue_publishes_the_leave_to_the_ledger(): void
    {
        $party = $this->employee();
        $request = $this->pendingRequest($party);

        Livewire::test(ListLeaveRequests::class)
            ->callAction(TestAction::make('approve_leave')->table($request), [
                'approved_comment' => 'Fine.',
                // The classification is made here, by the approver, not by the
                // employee who submitted the dates.
                'periods' => [[
                    'leave_type' => LeaveType::UNPAID->value,
                    'start_date' => '2026-06-01',
                    'end_date' => '2026-06-05',
                    'day_count' => 5,
                ]],
            ]);

        $this->assertSame(RequestStatus::APPROVED, $request->fresh()->status);
        $this->assertSame(1, PartyLeave::where('party_id', $party->id)->count());
        $this->assertSame(5.0, $request->fresh()->unpaidDays());
    }

    public function test_rejecting_from_the_queue_requires_a_reason(): void
    {
        $party = $this->employee();
        $request = $this->pendingRequest($party);

        Livewire::test(ListLeaveRequests::class)
            ->callAction(TestAction::make('reject_leave')->table($request), [
                'approved_comment' => null,
            ])
            ->assertHasActionErrors(['approved_comment' => 'required']);

        $this->assertSame(RequestStatus::PENDING, $request->fresh()->status);
    }

    public function test_an_employee_cannot_approve_their_own_request(): void
    {
        $party = $this->employee();
        $request = $this->pendingRequest($party);

        // The employee can see their own request — and must not be able to grant
        // it. Hiding the button would not be enough; the call itself is refused.
        $employee = $this->selfServiceUser($party);
        $this->actingAs($employee);

        $this->assertTrue($employee->can('viewAny', LeaveRequest::class));
        $this->assertFalse($employee->can('approve', $request));

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertActionHidden(TestAction::make('approve_leave')->table($request));

        $this->assertSame(RequestStatus::PENDING, $request->fresh()->status);
    }

    public function test_an_employee_sees_only_their_own_requests(): void
    {
        $mine = $this->employee();
        $theirs = $this->employee();

        $ownRequest = $this->pendingRequest($mine);
        $colleagueRequest = $this->pendingRequest($theirs);

        // The queue carries reasons for absence. An unscoped list would hand
        // every employee a readable history of their colleagues' circumstances.
        $this->actingAs($this->selfServiceUser($mine));

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords([$ownRequest])
            ->assertCanNotSeeTableRecords([$colleagueRequest]);
    }

    public function test_management_sees_every_request(): void
    {
        $first = $this->employee();
        $second = $this->employee();

        $requests = [$this->pendingRequest($first), $this->pendingRequest($second)];

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords($requests);
    }

    public function test_an_employee_cannot_file_a_request_for_a_colleague(): void
    {
        $mine = $this->employee();
        $colleague = $this->employee();

        $this->actingAs($this->selfServiceUser($mine));

        // The select offers this employee exactly one name — their own — so a
        // colleague's id fails the option list before it reaches the database.
        Livewire::test(CreateLeaveRequest::class)
            ->fillForm([
                'party_id' => $colleague->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ])
            ->call('create')
            ->assertHasFormErrors(['party_id']);

        $this->assertSame(0, LeaveRequest::where('party_id', $colleague->id)->count());
    }

    public function test_an_employee_files_against_their_own_record_without_choosing_it(): void
    {
        $mine = $this->employee();

        $this->actingAs($this->selfServiceUser($mine));

        // party_id is disabled for a non-management user, and a disabled field
        // is a courtesy rather than a barrier — so the page fills it in from the
        // signed-in user rather than trusting whatever arrives.
        Livewire::test(CreateLeaveRequest::class)
            ->fillForm([
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = LeaveRequest::sole();

        $this->assertSame($mine->id, $request->party_id);
        $this->assertSame(RequestStatus::PENDING, $request->status);
        $this->assertSame(0, $request->periods()->count());
    }

    public function test_management_may_file_on_behalf_of_an_employee(): void
    {
        $employee = $this->employee();

        Livewire::test(CreateLeaveRequest::class)
            ->fillForm([
                'party_id' => $employee->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, LeaveRequest::where('party_id', $employee->id)->count());
    }

    public function test_the_ladder_actions_appear_only_at_their_own_rung(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);

        $page = Livewire::test(ViewPayrollRun::class, ['record' => $run->getKey()]);

        // A draft can be generated but not approved by anyone.
        $page->assertActionVisible('generate')
            ->assertActionHidden('hr_approve')
            ->assertActionHidden('finance_approve')
            ->assertActionHidden('disburse');
    }

    public function test_the_journal_voucher_renders_and_balances_on_screen(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        Livewire::test(ViewPayrollRun::class, ['record' => $run->getKey()])
            ->mountAction('journal_voucher')
            ->assertOk();

        // Filament renders modal bodies lazily, so the view is exercised
        // directly rather than fished out of the component's initial HTML.
        $voucher = app(PayrollJournalVoucherService::class)->forRun($run);
        $html = view('filament.payroll.journal-voucher', ['voucher' => $voucher])->render();

        $this->assertTrue($voucher['balanced']);
        $this->assertStringContainsString(__('Salaries Expense'), $html);
        $this->assertStringContainsString(__('Net Salary Payable'), $html);
        $this->assertStringContainsString(__('Debits and credits agree.'), $html);
    }

    /**
     * A role holding nothing but the JV permission — the Finance-only user this
     * table action exists for.
     */
    private function journalVoucherOnlyUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'jv_only', 'guard_name' => 'web']);

        foreach (['ViewAny:PayrollRun', 'ViewJournalVoucher:PayrollRun'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role->givePermissionTo(['ViewAny:PayrollRun', 'ViewJournalVoucher:PayrollRun']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_the_eosg_closing_voucher_page_and_its_print_route_render(): void
    {
        $party = $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        // This page's own Shield-generated permission — separate from the
        // payroll module's seeded permissions, since HasPageShield derives it
        // independently of PayrollRun.
        $this->admin->givePermissionTo(Permission::findOrCreate('View:EndOfServiceGratuityClosingVoucher', 'web'));

        // The page mounts with the current year selected by default — no route
        // parameter to feed it one, since the year is chosen on the page itself.
        $this->get(EndOfServiceGratuityClosingVoucher::getUrl())
            ->assertSuccessful()
            ->assertSee($party->name);

        $this->get(route('payroll.eosg-closing-voucher.print', ['year' => 2026]))
            ->assertSuccessful()
            ->assertSee(__(':year — Annual Closing', ['year' => 2026]))
            ->assertSee($party->name);
    }

    public function test_the_jv_only_permission_reaches_the_voucher_straight_from_the_list(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        $this->actingAs($this->journalVoucherOnlyUser());

        Livewire::test(ListPayrollRuns::class)
            ->assertCanSeeTableRecords([$run])
            ->mountTableAction('journal_voucher', $run)
            ->assertOk();
    }

    public function test_the_jv_only_permission_does_not_reach_the_full_run_page(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::DRAFT]);
        app(PayrollService::class)->generate($run);

        $user = $this->journalVoucherOnlyUser();
        $this->actingAs($user);

        // The row's own View button needs View:PayrollRun, which this user does
        // not hold — that permission gap is the entire point of the JV-only
        // table action existing.
        $this->assertFalse($user->can('view', $run));
        $this->get(PayrollRunResource::getUrl('view', ['record' => $run]))->assertForbidden();
    }

    public function test_a_run_cannot_be_generated_once_it_has_left_draft(): void
    {
        $this->employee();

        $run = PayrollRun::create(['period' => '2026-06', 'status' => PayrollRunStatus::HR_REVIEW]);

        Livewire::test(ViewPayrollRun::class, ['record' => $run->getKey()])
            ->assertActionHidden('generate');
    }
}
