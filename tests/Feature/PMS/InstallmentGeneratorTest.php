<?php

namespace Tests\Feature\PMS;

use App\Models\Contract;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\TenantProfile;
use App\Models\Unit;
use App\Services\ContractService;
use App\Services\InstallmentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A 12-installment schedule must sum exactly to the contract's rent + VAT —
 * the rounding drift from an uneven split lands entirely on the last
 * installment, never spread invisibly across all of them.
 */
class InstallmentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InstallmentGenerator $generator;

    private ContractService $contracts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(InstallmentGenerator::class);
        $this->contracts = app(ContractService::class);
    }

    private function contractOn(Unit $unit, float $rent = 120000): Contract
    {
        $tenant = Party::factory()->tenant()->create();

        return $this->contracts->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'grace_period_days' => 5,
            'total_base_rent' => $rent,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);
    }

    public function test_a_twelve_installment_schedule_on_a_commercial_contract_sums_exactly(): void
    {
        $unit = Unit::factory()->commercial()->create();
        $contract = $this->contractOn($unit, 120000);

        $installments = $this->generator->generateSchedule($contract, 12);

        $this->assertCount(12, $installments);

        $totalNet = $installments->sum(fn ($installment) => (float) $installment->net_amount);
        $totalVat = $installments->sum(fn ($installment) => (float) $installment->vat_amount);
        $totalDue = $installments->sum(fn ($installment) => (float) $installment->total_due_amount);

        $this->assertSame(120000.0, round($totalNet, 2));
        $this->assertSame(6000.0, round($totalVat, 2)); // 5% of 120,000
        $this->assertSame(126000.0, round($totalDue, 2));

        // Every installment carries its own frozen VAT rate and serial.
        $this->assertTrue($installments->every(fn ($i) => (float) $i->vat_rate === 0.05));
        $this->assertSame(
            range(1, 12),
            $installments->map(fn ($i) => (int) substr($i->tax_invoice_serial, -2))->all(),
        );
    }

    public function test_a_residential_contract_has_no_vat(): void
    {
        $unit = Unit::factory()->residential()->create();
        $contract = $this->contractOn($unit, 60000);

        $installments = $this->generator->generateSchedule($contract, 4);

        $this->assertTrue($installments->every(fn ($i) => (float) $i->vat_amount === 0.0));
    }

    public function test_grace_period_expiry_is_due_date_plus_the_contracts_grace_days(): void
    {
        $unit = Unit::factory()->residential()->create();
        $contract = $this->contractOn($unit, 60000);

        $installments = $this->generator->generateSchedule($contract, 1);
        $installment = $installments->first();

        $this->assertSame(
            $installment->due_date->copy()->addDays(5)->toDateString(),
            $installment->grace_period_expiry_date->toDateString(),
        );
    }

    public function test_tax_invoice_stamps_landlord_and_tenant_trn(): void
    {
        $unit = Unit::factory()->commercial()->create();
        $owner = Party::factory()->owner()->create();
        $unit->building->owners()->attach($owner->id, ['ownership_percentage' => 100]);
        OwnerProfile::create(['party_id' => $owner->id, 'trn' => '100000000000001']);

        $contract = $this->contractOn($unit, 60000);
        $tenantParty = $contract->primaryTenant()->party;
        TenantProfile::create([
            'party_id' => $tenantParty->id,
            'tenant_type' => 'person',
            'identification_type' => 'emirates_id',
            'identification_number' => '784-1990-1234567-1',
            'trn' => '100000000000002',
        ]);

        $installments = $this->generator->generateSchedule($contract->fresh(), 1);
        $installment = $installments->first();

        $this->assertSame('100000000000001', $installment->landlord_trn);
        $this->assertSame('100000000000002', $installment->tenant_trn);
    }

    public function test_a_contract_cannot_be_scheduled_twice(): void
    {
        $unit = Unit::factory()->residential()->create();
        $contract = $this->contractOn($unit, 60000);

        $this->generator->generateSchedule($contract, 4);

        $this->expectException(RuntimeException::class);

        $this->generator->generateSchedule($contract, 4);
    }

    public function test_a_tax_exempt_contract_has_no_vat_even_on_a_commercial_unit(): void
    {
        $unit = Unit::factory()->commercial()->create();
        $contract = $this->contractOn($unit, 60000);
        $contract->update(['tax_exemption_reason' => 'Transfer of Going Concern']);

        $installments = $this->generator->generateSchedule($contract->fresh(), 1);

        $this->assertSame(0.0, (float) $installments->first()->vat_amount);
    }
}
