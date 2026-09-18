<?php

namespace Tests\Unit\PMS;

use App\Filament\Pms\Imports\PropertyImporter;
use App\Models\Property;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(array $data): PropertyImporter
    {
        $importer = new PropertyImporter(new Import, [], []);

        $reflection = new \ReflectionProperty(PropertyImporter::class, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $data);

        return $importer;
    }

    public function test_it_creates_a_new_property_by_name(): void
    {
        $record = $this->makeImporter(['name' => 'Marina Tower'])->resolveRecord();

        $this->assertInstanceOf(Property::class, $record);
        $this->assertFalse($record->exists);
        $this->assertSame('Marina Tower', $record->name);
    }

    public function test_re_importing_the_same_property_name_updates_it_instead_of_duplicating(): void
    {
        $existing = Property::factory()->create(['name' => 'Marina Tower']);

        $record = $this->makeImporter(['name' => 'Marina Tower'])->resolveRecord();

        $this->assertTrue($record->exists);
        $this->assertSame($existing->id, $record->id);
    }
}
