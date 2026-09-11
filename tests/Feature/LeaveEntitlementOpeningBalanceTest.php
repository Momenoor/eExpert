<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Party;
use App\Services\LeaveEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The opening leave balance: days carried over from before this system
 * tracked leave, added once to an employee's very first service-year row.
 */
class LeaveEntitlementOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveEntitlementService $entitlements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entitlements = app(LeaveEntitlementService::class);
    }

    private function employee(string $joinedOn, float $openingBalance = 0.0): Party
    {
        $party = Party::factory()->employee()->create();

        EmployeeProfile::create([
            'party_id' => $party->id,
            'date_of_joining' => $joinedOn,
            'opening_leave_balance' => $openingBalance,
        ]);

        return $party->fresh();
    }

    public function test_the_opening_balance_tops_up_the_first_service_year_row(): void
    {
        $party = $this->employee('2020-01-01', 12.0);

        $entitlement = $this->entitlements->forDate($party, Carbon::parse('2026-06-01'));

        // 30 statutory days plus the 12 carried over from before this system
        // existed.
        $this->assertSame('42.0', $entitlement->annual_entitled_days);
    }

    public function test_the_opening_balance_is_not_reapplied_to_a_later_service_year(): void
    {
        $party = $this->employee('2020-01-01', 12.0);

        // Force the first row to exist for an earlier service year.
        $firstEntitlement = $this->entitlements->forDate($party, Carbon::parse('2024-06-01'));
        $this->assertSame('42.0', $firstEntitlement->annual_entitled_days);

        $laterEntitlement = $this->entitlements->forDate($party, Carbon::parse('2026-06-01'));

        $this->assertSame('30.0', $laterEntitlement->annual_entitled_days);
    }

    public function test_no_opening_balance_leaves_the_statutory_figure_untouched(): void
    {
        $party = $this->employee('2020-01-01');

        $entitlement = $this->entitlements->forDate($party, Carbon::parse('2026-06-01'));

        $this->assertSame('30.0', $entitlement->annual_entitled_days);
    }
}
