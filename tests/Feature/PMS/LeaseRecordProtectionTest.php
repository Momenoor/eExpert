<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeasePartyRole;
use App\Filament\Pms\Resources\Properties\Pages\EditProperty;
use App\Filament\Pms\Resources\Properties\Pages\ListProperties;
use App\Filament\Pms\Resources\Properties\RelationManagers\UnitsRelationManager;
use App\Filament\Pms\Resources\Tenants\Pages\ListTenants;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\LeaseService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property/Unit/Tenant are a lease's own history once one exists — deleting
 * any of them would leave a contract pointing at nothing, so all three
 * refuse it, at the model level (authoritative, works from anywhere) and in
 * the Filament UI (a notification instead of a raw exception).
 */
class LeaseRecordProtectionTest extends TestCase
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

    private function leasedUnit(): Unit
    {
        $unit = Unit::factory()->residential()->create();

        app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
        ], [
            ['party_id' => Party::factory()->tenant()->create()->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        return $unit;
    }

    public function test_a_unit_linked_to_a_lease_cannot_be_deleted(): void
    {
        $unit = $this->leasedUnit();

        $this->expectException(RuntimeException::class);

        $unit->delete();
    }

    public function test_a_unit_with_no_lease_can_be_deleted(): void
    {
        $unit = Unit::factory()->residential()->create();

        $unit->delete();

        $this->assertSoftDeleted($unit);
    }

    public function test_a_property_with_a_leased_unit_cannot_be_deleted(): void
    {
        $unit = $this->leasedUnit();

        $this->expectException(RuntimeException::class);

        $unit->property->delete();
    }

    public function test_a_tenant_linked_to_a_lease_cannot_be_deleted(): void
    {
        $unit = Unit::factory()->residential()->create();
        $party = Party::factory()->tenant()->create();
        $tenant = Tenant::factory()->create(['party_id' => $party->id]);

        app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $party->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        $this->expectException(RuntimeException::class);

        $tenant->delete();
    }

    public function test_deleting_a_leased_unit_from_the_relation_manager_shows_a_notification_instead_of_deleting(): void
    {
        $unit = $this->leasedUnit();

        Livewire::test(UnitsRelationManager::class, ['ownerRecord' => $unit->property, 'pageClass' => EditProperty::class])
            ->callAction(TestAction::make('delete')->table($unit))
            ->assertNotified();

        $this->assertNotSoftDeleted($unit);
    }

    public function test_deleting_a_leased_property_from_the_list_page_shows_a_notification_instead_of_deleting(): void
    {
        $unit = $this->leasedUnit();
        $property = $unit->property;

        Livewire::test(ListProperties::class)
            ->callAction(TestAction::make('delete')->table($property))
            ->assertNotified();

        $this->assertNotSoftDeleted($property);
    }

    public function test_deleting_a_leased_tenant_from_the_list_page_shows_a_notification_instead_of_deleting(): void
    {
        $unit = Unit::factory()->residential()->create();
        $party = Party::factory()->tenant()->create();
        $tenant = Tenant::factory()->create(['party_id' => $party->id]);

        app(LeaseService::class)->createFromRawInputs([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $party->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('delete')->table($tenant))
            ->assertNotified();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }
}
