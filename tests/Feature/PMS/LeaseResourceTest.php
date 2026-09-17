<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\LeaseStatus;
use App\Filament\Pms\Resources\Leases\Pages\CreateLease;
use App\Filament\Pms\Resources\Leases\Pages\ViewLease;
use App\Filament\Pms\Resources\Quotations\Pages\ViewQuotation;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\LeaseService;
use App\Services\PMS\QuotationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaseResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pms'));

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_creating_a_contract_through_the_form(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        Livewire::test(CreateLease::class)
            ->fillForm([
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'total_base_rent' => 70000,
                'tenants' => [
                    ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
                ],
                'units' => [$unit->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lease = Lease::sole();
        $this->assertSame(LeaseStatus::PENDING_ATTESTATION, $lease->status);
        $this->assertCount(1, $lease->units);
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

        $this->assertSame(1, Lease::count());
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

        $lease = app(LeaseService::class)->createFromQuotation($quotation->fresh());

        Livewire::test(ViewLease::class, ['record' => $lease->getKey()])
            ->callAction('attest', [
                'attestation_system' => 'ejari_dubai',
                'attestation_serial_number' => 'EJ-99999',
            ]);

        $this->assertSame(LeaseStatus::ACTIVE, $lease->fresh()->status);
    }

    public function test_view_page_evaluate_renewal_action_does_not_mutate_the_contract(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $unit = Unit::factory()->residential()->create();

        $lease = app(LeaseService::class)->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 80000,
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        Livewire::test(ViewLease::class, ['record' => $lease->getKey()])
            ->callAction('evaluate_renewal', [
                'target_renewal_date' => now()->toDateString(),
                'market_average_rent' => 100000,
            ])
            ->assertHasNoActionErrors();

        // A what-if, not a mutation — the lease's own rent is untouched.
        $this->assertSame('80000.00', $lease->fresh()->total_base_rent);
    }
}
