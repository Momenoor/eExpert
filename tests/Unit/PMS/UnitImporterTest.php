<?php

namespace Tests\Unit\PMS;

use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitType;
use App\Filament\Pms\Imports\UnitImporter;
use App\Models\Property;
use App\Models\Unit;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(array $data): UnitImporter
    {
        $importer = new UnitImporter(new Import, [], []);

        $reflection = new \ReflectionProperty(UnitImporter::class, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $data);

        return $importer;
    }

    public function test_it_matches_the_property_by_name_and_defaults_classification_from_the_unit_type(): void
    {
        Property::factory()->create(['name' => 'Marina Tower']);

        $record = $this->makeImporter([
            'property_name' => 'Marina Tower',
            'unit_number' => '101',
            'unit_type' => UnitType::APARTMENT->value,
        ])->resolveRecord();

        $this->assertInstanceOf(Unit::class, $record);
        $this->assertFalse($record->exists);
        $this->assertSame('101', $record->unit_number);
        $this->assertSame(PropertyClassification::RESIDENTIAL, $record->property_classification);
    }

    public function test_re_importing_the_same_unit_updates_it_instead_of_duplicating(): void
    {
        $property = Property::factory()->create(['name' => 'Marina Tower']);
        $existing = Unit::factory()->for($property)->create(['unit_number' => '101']);

        $record = $this->makeImporter([
            'property_name' => 'Marina Tower',
            'unit_number' => '101',
            'unit_type' => UnitType::APARTMENT->value,
        ])->resolveRecord();

        $this->assertTrue($record->exists);
        $this->assertSame($existing->id, $record->id);
    }

    public function test_it_fails_gracefully_when_the_property_does_not_exist(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->makeImporter([
            'property_name' => 'Nonexistent Tower',
            'unit_number' => '101',
            'unit_type' => UnitType::APARTMENT->value,
        ])->resolveRecord();
    }
}
