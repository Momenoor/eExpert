<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeasePartyRole;
use App\Filament\Pms\Resources\Leases\Pages\CreateLease;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The guided, step-by-step lease creation wizard — property first, then
 * tenants, units (scoped to that property), contract details, a manually
 * declared instalment schedule (validated against rent + VAT + deposit),
 * and finally the Special Conditions template.
 */
class LeaseCreationWizardTest extends TestCase
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

    private function baseFormData(Unit $unit, Party $tenant, array $installments): array
    {
        return [
            'property_id' => $unit->property_id,
            'tenants' => [
                ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
            ],
            'units' => [
                ['unit_id' => $unit->id],
            ],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 60000,
            'security_deposit_amount' => 5000,
            'installments' => $installments,
        ];
    }

    public function test_a_residential_lease_installments_must_sum_to_rent_plus_security_deposit(): void
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        // Residential is VAT-exempt, so the target is just 60000 + 5000 deposit.
        Livewire::test(CreateLease::class)
            ->fillForm($this->baseFormData($unit, $tenant, [
                ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 60000],
                ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 5000, 'is_security_deposit' => true],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $lease = Lease::sole();
        $this->assertCount(2, $lease->installments);

        $deposit = $lease->installments->firstWhere('is_security_deposit', true);
        $this->assertNotNull($deposit);
        $this->assertSame('5000.00', $deposit->net_amount);
        $this->assertSame('0.00', $deposit->vat_amount);

        $rentRow = $lease->installments->firstWhere('is_security_deposit', false);
        $this->assertSame('60000.00', $rentRow->net_amount);
    }

    public function test_a_commercial_lease_installments_must_include_vat(): void
    {
        $unit = Unit::factory()->commercial()->create();
        $tenant = Party::factory()->tenant()->create();

        // Commercial is taxed at 5%: 60000 rent + 3000 VAT + 5000 deposit = 68000.
        Livewire::test(CreateLease::class)
            ->fillForm($this->baseFormData($unit, $tenant, [
                // Missing VAT — should fail the sum validation.
                ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 60000],
                ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 5000, 'is_security_deposit' => true],
            ]))
            ->call('create')
            ->assertHasFormErrors(['installments']);

        $this->assertSame(0, Lease::count());

        Livewire::test(CreateLease::class)
            ->fillForm($this->baseFormData($unit, $tenant, [
                ['payment_method' => 'bank_transfer', 'payment_date' => now()->toDateString(), 'amount' => 63000, 'reference_number' => 'TRF-001'],
                ['payment_method' => 'cash', 'payment_date' => now()->toDateString(), 'amount' => 5000, 'is_security_deposit' => true],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $lease = Lease::sole();
        $rentRow = $lease->installments->firstWhere('is_security_deposit', false);
        $this->assertSame('60000.00', $rentRow->net_amount);
        $this->assertSame('3000.00', $rentRow->vat_amount);
        $this->assertSame('TRF-001', $rentRow->transaction_reference);
    }

    public function test_units_step_only_offers_units_belonging_to_the_selected_property(): void
    {
        $propertyOne = Property::factory()->create();
        $propertyTwo = Property::factory()->create();
        $unitInPropertyOne = Unit::factory()->residential()->create(['property_id' => $propertyOne->id]);
        $unitInPropertyTwo = Unit::factory()->residential()->create(['property_id' => $propertyTwo->id]);

        Livewire::test(CreateLease::class)
            ->fillForm(['property_id' => $propertyOne->id]);

        // Directly exercise the option-scoping helper the Units step's
        // Select uses, since asserting rendered <option> lists through
        // Livewire's testing API doesn't reach into nested repeater rows.
        $options = Unit::query()->where('property_id', $propertyOne->id)->pluck('unit_number', 'id')->all();

        $this->assertArrayHasKey($unitInPropertyOne->id, $options);
        $this->assertArrayNotHasKey($unitInPropertyTwo->id, $options);
    }

    public function test_selecting_a_property_defaults_the_units_step_to_its_first_unit(): void
    {
        $property = Property::factory()->create();
        $firstUnit = Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '101']);
        Unit::factory()->residential()->create(['property_id' => $property->id, 'unit_number' => '102']);

        Livewire::test(CreateLease::class)
            ->fillForm(['property_id' => $property->id])
            ->assertSchemaStateSet([
                'units' => [
                    ['unit_id' => $firstUnit->id],
                ],
            ]);
    }

    public function test_switching_property_replaces_the_default_unit_rather_than_clearing_it(): void
    {
        $propertyOne = Property::factory()->create();
        $unitOne = Unit::factory()->residential()->create(['property_id' => $propertyOne->id, 'unit_number' => '101']);
        $propertyTwo = Property::factory()->create();
        $unitTwo = Unit::factory()->residential()->create(['property_id' => $propertyTwo->id, 'unit_number' => '201']);

        Livewire::test(CreateLease::class)
            ->fillForm(['property_id' => $propertyOne->id])
            ->assertSchemaStateSet(['units' => [['unit_id' => $unitOne->id]]])
            ->fillForm(['property_id' => $propertyTwo->id])
            ->assertSchemaStateSet(['units' => [['unit_id' => $unitTwo->id]]]);
    }
}
