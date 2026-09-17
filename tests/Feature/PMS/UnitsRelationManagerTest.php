<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitType;
use App\Filament\Pms\Resources\Properties\Pages\EditProperty;
use App\Filament\Pms\Resources\Properties\RelationManagers\UnitsRelationManager;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('pms'));
    }

    public function test_units_relation_manager_lists_the_properties_units(): void
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create([
            'unit_number' => '101',
            'unit_type' => UnitType::COMMERCIAL_OFFICE,
            'property_classification' => PropertyClassification::COMMERCIAL,
        ]);

        Livewire::test(UnitsRelationManager::class, [
            'ownerRecord' => $property,
            'pageClass' => EditProperty::class,
        ])->assertCanSeeTableRecords([$unit]);
    }

    public function test_a_unit_created_directly_on_the_property_carries_the_right_vat_rate(): void
    {
        $property = Property::factory()->create();

        $unit = $property->units()->create([
            'unit_number' => '101',
            'unit_type' => UnitType::COMMERCIAL_OFFICE,
            'property_classification' => PropertyClassification::COMMERCIAL,
            'rental_rate' => 50000,
        ]);

        $this->assertSame('101', $unit->unit_number);
        $this->assertSame(0.05, $unit->vatRate());
    }
}
