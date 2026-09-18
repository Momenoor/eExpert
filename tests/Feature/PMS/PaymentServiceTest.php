<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\MMS\PaymentService;
use App\Services\PMS\InstallmentGenerator;
use App\Services\PMS\LeaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();

        $this->payments = app(PaymentService::class);
    }

    private function installment(float $netAmount = 10000): Installment
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        /** @var Lease $lease */
        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->addDay()->toDateString(),
            'total_base_rent' => $netAmount,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);

        return app(InstallmentGenerator::class)->generateSchedule($lease, 1)->first();
    }

    public function test_a_full_payment_marks_the_installment_paid(): void
    {
        $installment = $this->installment(10000);

        $this->payments->recordPayment($installment, ['amount' => 10000]);

        $installment = $installment->fresh();
        $this->assertSame(InstallmentPaymentStatus::PAID, $installment->payment_status);
        $this->assertSame('0.00', $installment->balance_due);
    }

    public function test_a_partial_payment_recomputes_balance_and_status(): void
    {
        $installment = $this->installment(10000);

        $this->payments->recordPayment($installment, ['amount' => 4000]);

        $installment = $installment->fresh();
        $this->assertSame(InstallmentPaymentStatus::PARTIAL, $installment->payment_status);
        $this->assertSame('4000.00', $installment->paid_amount);
        $this->assertSame('6000.00', $installment->balance_due);
    }

    public function test_two_partial_payments_accumulate_toward_paid(): void
    {
        $installment = $this->installment(10000);

        $this->payments->recordPayment($installment, ['amount' => 4000]);
        $this->payments->recordPayment($installment->fresh(), ['amount' => 6000]);

        $installment = $installment->fresh();
        $this->assertSame(InstallmentPaymentStatus::PAID, $installment->payment_status);
        $this->assertSame('0.00', $installment->balance_due);
    }

    public function test_two_partial_payments_each_create_their_own_ledger_row(): void
    {
        $installment = $this->installment(10000);

        $this->payments->recordPayment($installment, ['amount' => 4000, 'payment_method' => 'cash']);
        $this->payments->recordPayment($installment->fresh(), ['amount' => 6000, 'payment_method' => 'bank_transfer']);

        $payments = $installment->fresh()->payments;
        $this->assertCount(2, $payments);
        $this->assertSame('4000.00', $payments->first()->amount);
        $this->assertSame('6000.00', $payments->last()->amount);
    }

    public function test_a_fully_paid_installment_refuses_a_further_payment(): void
    {
        $installment = $this->installment(10000);
        $this->payments->recordPayment($installment, ['amount' => 10000]);

        $this->expectException(RuntimeException::class);

        $this->payments->recordPayment($installment->fresh(), ['amount' => 100]);
    }

    public function test_a_zero_or_negative_payment_is_refused(): void
    {
        $installment = $this->installment(10000);

        $this->expectException(RuntimeException::class);

        $this->payments->recordPayment($installment, ['amount' => 0]);
    }

    public function test_marking_a_cheque_bounced_reverts_paid_amount_and_adds_a_penalty(): void
    {
        Setting::set('pms_bounced_cheque_penalty', 150);

        $installment = $this->installment(10000);
        $this->payments->recordPayment($installment, ['amount' => 4000]);

        $this->payments->markBounced($installment->fresh());

        $installment = $installment->fresh();
        $this->assertSame(InstallmentPaymentStatus::BOUNCED, $installment->payment_status);
        $this->assertSame('0.00', $installment->paid_amount);
        $this->assertSame('150.00', $installment->admin_penalty_amount);
        $this->assertSame('10150.00', $installment->balance_due);
    }

    public function test_flagging_overdue_only_applies_past_the_grace_period(): void
    {
        $installment = $this->installment(10000);

        $this->payments->flagOverdueIfNeeded($installment);
        $this->assertSame(InstallmentPaymentStatus::PENDING, $installment->fresh()->payment_status);

        $installment->forceFill(['grace_period_expiry_date' => now()->subDay()])->save();

        $this->payments->flagOverdueIfNeeded($installment->fresh());
        $this->assertSame(InstallmentPaymentStatus::OVERDUE, $installment->fresh()->payment_status);
    }

    public function test_flagging_overdue_never_touches_a_paid_installment(): void
    {
        $installment = $this->installment(10000);
        $this->payments->recordPayment($installment, ['amount' => 10000]);
        $installment->forceFill(['grace_period_expiry_date' => now()->subDay()])->save();

        $this->payments->flagOverdueIfNeeded($installment->fresh());

        $this->assertSame(InstallmentPaymentStatus::PAID, $installment->fresh()->payment_status);
    }
}
