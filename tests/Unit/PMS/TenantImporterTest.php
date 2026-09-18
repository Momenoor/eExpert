<?php

namespace Tests\Unit\PMS;

use App\Enums\PMS\TenantIdentificationType;
use App\Filament\Pms\Imports\TenantImporter;
use App\Models\Tenant;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(array $data): TenantImporter
    {
        $importer = new TenantImporter(new Import, [], []);

        $reflection = new \ReflectionProperty(TenantImporter::class, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $data);

        return $importer;
    }

    public function test_it_creates_a_party_and_tenant_together(): void
    {
        $record = $this->makeImporter([
            'name' => 'Madhav Chaturvedi',
            'identification_type' => TenantIdentificationType::EMIRATES_ID->value,
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();

        $this->assertInstanceOf(Tenant::class, $record);
        $this->assertFalse($record->exists);
        $this->assertNotNull($record->party_id);
        $this->assertSame('Madhav Chaturvedi', $record->party->name);
        $this->assertTrue($record->party->isTenant());
    }

    public function test_re_importing_the_same_identification_number_updates_the_existing_tenant(): void
    {
        // resolveRecord() alone only sets party_id/identification_number —
        // the rest of a row's columns are filled by Filament's own
        // fillRecord() pipeline afterward, so it's set explicitly here to
        // save this first row directly without going through that pipeline.
        $record = $this->makeImporter([
            'name' => 'Madhav Chaturvedi',
            'identification_type' => TenantIdentificationType::EMIRATES_ID->value,
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();
        $record->identification_type = TenantIdentificationType::EMIRATES_ID;
        $record->save();

        $updated = $this->makeImporter([
            'name' => 'Madhav C. Chaturvedi',
            'identification_type' => TenantIdentificationType::EMIRATES_ID->value,
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();

        $this->assertTrue($updated->exists);
        $this->assertSame($record->id, $updated->id);
        $this->assertSame('Madhav C. Chaturvedi', $updated->party->name);
    }
}
