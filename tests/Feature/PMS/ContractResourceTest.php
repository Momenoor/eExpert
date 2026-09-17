<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\ContractPartyRole;
use App\Enums\PMS\ContractStatus;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Models\Contract;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContractService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_creating_a_contract_through_the_form(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        Livewire::test(CreateContract::class)
            ->fillForm([
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'total_base_rent' => 70000,
                'tenants' => [
                    ['party_id' => $tenant->id, 'role' => ContractPartyRole::PRIMARY_TENANT->value],
                ],
                'units' => [$unit->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $contract = Contract::sole();
        $this->assertSame(ContractStatus::PENDING_ATTESTATION, $contract->status);
        $this->assertCount(1, $contract->units);
    }

    public function test_converting_an_accepted_quotation_creates_a_contract(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        $quotation = app(QuotationService::class)->generate([
            'party_id' => $tenant->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 60000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);
        app(QuotationService::class)->send($quotation);
        app(QuotationService::class)->accept($quotation);

        Livewire::test(ViewQuotation::class, ['record' => $quotation->getKey()])
            ->callAction('convert_to_contract');

        $this->assertSame(1, Contract::count());
    }

    public function test_view_page_attest_action_activates_the_contract(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        $quotation = app(QuotationService::class)->generate([
            'party_id' => $tenant->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 60000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);
        app(QuotationService::class)->send($quotation);
        app(QuotationService::class)->accept($quotation);

        $contract = app(ContractService::class)->createFromQuotation($quotation->fresh());

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->callAction('attest', [
                'attestation_system' => 'ejari_dubai',
                'attestation_serial_number' => 'EJ-99999',
            ]);

        $this->assertSame(ContractStatus::ACTIVE, $contract->fresh()->status);
    }

    public function test_view_page_evaluate_renewal_action_does_not_mutate_the_contract(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        $contract = app(ContractService::class)->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 80000,
        ], [
            ['party_id' => $tenant->id, 'role' => ContractPartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->callAction('evaluate_renewal', [
                'target_renewal_date' => now()->toDateString(),
                'market_average_rent' => 100000,
            ])
            ->assertHasNoActionErrors();

        // A what-if, not a mutation — the contract's own rent is untouched.
        $this->assertSame('80000.00', $contract->fresh()->total_base_rent);
    }
}
