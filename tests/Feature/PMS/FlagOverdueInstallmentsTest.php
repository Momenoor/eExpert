<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Contract;
use App\Models\Party;
use App\Models\Unit;
use App\Services\ContractService;
use App\Services\InstallmentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `pms:flag-overdue-installments` is a thin wrapper around
 * `PaymentService::flagOverdueIfNeeded()` — this asserts the command itself
 * finds the right rows and calls through, not the flagging rule again (that
 * belongs to `PaymentServiceTest`).
 */
class FlagOverdueInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    private function contract(): Contract
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(ContractService::class)->createFromRawInputs([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);
    }

    public function test_it_flags_installments_past_their_grace_period(): void
    {
        $contract = $this->contract();
        $installment = app(InstallmentGenerator::class)->generateSchedule($contract, 1)->first();
        $installment->forceFill(['grace_period_expiry_date' => now()->subDay()])->save();

        $this->artisan('pms:flag-overdue-installments')->assertSuccessful();

        $this->assertSame(InstallmentPaymentStatus::OVERDUE, $installment->fresh()->payment_status);
    }

    public function test_it_leaves_installments_still_within_grace_period_alone(): void
    {
        $contract = $this->contract();
        $installment = app(InstallmentGenerator::class)->generateSchedule($contract, 1)->first();

        $this->artisan('pms:flag-overdue-installments')->assertSuccessful();

        $this->assertSame(InstallmentPaymentStatus::PENDING, $installment->fresh()->payment_status);
    }

    public function test_a_bad_row_does_not_stop_the_rest_from_being_flagged(): void
    {
        $contractOne = $this->contract();
        $installmentOne = app(InstallmentGenerator::class)->generateSchedule($contractOne, 1)->first();
        $installmentOne->forceFill(['grace_period_expiry_date' => now()->subDay()])->save();

        $contractTwo = $this->contract();
        $installmentTwo = app(InstallmentGenerator::class)->generateSchedule($contractTwo, 1)->first();
        $installmentTwo->forceFill(['grace_period_expiry_date' => now()->subDay()])->save();

        $this->artisan('pms:flag-overdue-installments')->assertSuccessful();

        $this->assertSame(InstallmentPaymentStatus::OVERDUE, $installmentOne->fresh()->payment_status);
        $this->assertSame(InstallmentPaymentStatus::OVERDUE, $installmentTwo->fresh()->payment_status);
    }
}
