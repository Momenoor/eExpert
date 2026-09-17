<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\ContractPartyRole;
use App\Enums\PMS\ContractStatus;
use App\Models\ContractParty;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\Unit;
use App\Services\ContractService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Drafting a contract — from an accepted quotation, or from raw inputs — and
 * attaching its tenants/units. Status only ever moves forward through this
 * service, never hand-edited on the record.
 */
class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $contracts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contracts = app(ContractService::class);
    }

    private function tenant(): Party
    {
        return Party::factory()->tenant()->create();
    }

    private function acceptedQuotation(): Quotation
    {
        $tenant = $this->tenant();
        $unit = Unit::factory()->residential()->create();

        $quotation = app(QuotationService::class)->generate([
            'party_id' => $tenant->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 60000]],
            'security_deposit' => 5000,
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        app(QuotationService::class)->send($quotation);
        app(QuotationService::class)->accept($quotation);

        return $quotation->fresh();
    }

    public function test_creating_a_contract_from_an_accepted_quotation_carries_over_the_tenant_unit_and_figures(): void
    {
        $quotation = $this->acceptedQuotation();

        $contract = $this->contracts->createFromQuotation($quotation);

        $this->assertSame(ContractStatus::PENDING_ATTESTATION, $contract->status);
        $this->assertSame(AttestationStatus::UNREGISTERED, $contract->attestation_status);
        $this->assertSame('60000.00', $contract->total_base_rent);
        $this->assertSame('5000.00', $contract->security_deposit_amount);

        $primaryTenant = $contract->primaryTenant();
        $this->assertNotNull($primaryTenant);
        $this->assertSame($quotation->party_id, $primaryTenant->party_id);
        $this->assertSame(ContractPartyRole::PRIMARY_TENANT, $primaryTenant->role);

        $this->assertCount(1, $contract->units);
    }

    public function test_a_draft_quotation_cannot_become_a_contract(): void
    {
        $tenant = $this->tenant();
        $unit = Unit::factory()->residential()->create();

        $quotation = app(QuotationService::class)->generate([
            'party_id' => $tenant->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 60000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->contracts->createFromQuotation($quotation);
    }

    public function test_creating_a_contract_with_a_guarantor_links_it_to_the_primary_tenant(): void
    {
        $primary = $this->tenant();
        $guarantor = $this->tenant();
        $unit = Unit::factory()->residential()->create();

        $contract = $this->contracts->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 80000,
        ], [
            ['party_id' => $primary->id, 'role' => ContractPartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $primaryRow = $contract->primaryTenant();
        $this->assertNotNull($primaryRow);

        // A guarantor added after the fact, linked back to the primary
        // tenant's own contract_party row — mirrors matter_party's
        // representative-to-plaintiff link.
        $guarantorRow = ContractParty::create([
            'contract_id' => $contract->id,
            'party_id' => $guarantor->id,
            'role' => ContractPartyRole::GUARANTOR->value,
            'parent_id' => $primaryRow->id,
        ]);

        $this->assertTrue($primaryRow->fresh()->guarantors->contains($guarantorRow));
    }

    public function test_raw_creation_requires_at_least_one_tenant(): void
    {
        $unit = Unit::factory()->residential()->create();

        $this->expectException(RuntimeException::class);

        $this->contracts->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 80000,
        ], [], [$unit->id]);
    }

    public function test_raw_creation_requires_at_least_one_unit(): void
    {
        $tenant = $this->tenant();

        $this->expectException(RuntimeException::class);

        $this->contracts->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 80000,
        ], [['party_id' => $tenant->id]], []);
    }

    public function test_attesting_a_contract_activates_it(): void
    {
        $quotation = $this->acceptedQuotation();
        $contract = $this->contracts->createFromQuotation($quotation);

        $this->contracts->attest($contract, [
            'attestation_system' => 'ejari_dubai',
            'attestation_serial_number' => 'EJ-12345',
        ]);

        $contract = $contract->fresh();
        $this->assertSame(ContractStatus::ACTIVE, $contract->status);
        $this->assertSame(AttestationStatus::REGISTERED, $contract->attestation_status);
        $this->assertSame('EJ-12345', $contract->attestation_serial_number);
    }

    public function test_drafting_a_contract_marks_its_units_occupied(): void
    {
        $unit = Unit::factory()->residential()->create();
        $this->assertTrue($unit->isVacant());

        $this->contracts->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $this->tenant()->id, 'role' => ContractPartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->assertFalse($unit->fresh()->isVacant());
    }

    public function test_terminating_a_contract_releases_its_units_back_to_vacant(): void
    {
        $quotation = $this->acceptedQuotation();
        $unit = $quotation->units->first();
        $contract = $this->contracts->createFromQuotation($quotation);

        $this->assertFalse($unit->fresh()->isVacant());

        $this->contracts->terminate($contract);

        $this->assertTrue($unit->fresh()->isVacant());
    }

    public function test_terminating_a_contract_cannot_be_done_twice(): void
    {
        $quotation = $this->acceptedQuotation();
        $contract = $this->contracts->createFromQuotation($quotation);

        $this->contracts->terminate($contract);
        $this->assertSame(ContractStatus::TERMINATED, $contract->fresh()->status);

        $this->expectException(RuntimeException::class);

        $this->contracts->terminate($contract->fresh());
    }
}
