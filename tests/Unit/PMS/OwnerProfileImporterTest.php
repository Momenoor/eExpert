<?php

namespace Tests\Unit\PMS;

use App\Filament\Pms\Imports\OwnerProfileImporter;
use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerProfileImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(array $data): OwnerProfileImporter
    {
        $importer = new OwnerProfileImporter(new Import, [], []);

        $reflection = new \ReflectionProperty(OwnerProfileImporter::class, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $data);

        return $importer;
    }

    public function test_it_creates_a_party_and_owner_profile_together(): void
    {
        $record = $this->makeImporter([
            'name' => 'Mahmoud Kalbat',
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();

        $this->assertInstanceOf(OwnerProfile::class, $record);
        $this->assertFalse($record->exists);
        $this->assertNotNull($record->party_id);
        $this->assertSame('Mahmoud Kalbat', $record->party->name);
        $this->assertTrue($record->party->isOwner());
    }

    public function test_re_importing_the_same_identification_number_updates_the_existing_profile(): void
    {
        $record = $this->makeImporter([
            'name' => 'Mahmoud Kalbat',
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();
        $record->save();

        $updated = $this->makeImporter([
            'name' => 'Mahmoud Kalbat Al Kalbat',
            'identification_number' => '784-1990-1234567-1',
        ])->resolveRecord();

        $this->assertTrue($updated->exists);
        $this->assertSame($record->id, $updated->id);
        $this->assertSame('Mahmoud Kalbat Al Kalbat', $updated->party->name);
    }

    public function test_an_owner_group_name_links_to_an_existing_or_new_owner_group(): void
    {
        $record = $this->makeImporter([
            'name' => 'Mahmoud Kalbat',
            'identification_number' => '784-1990-1234567-1',
            'owner_group_name' => 'Legal Heirs of Mahmoud Kalbat',
        ])->resolveRecord();

        $this->assertNotNull($record->owner_group_id);
        $this->assertSame('Legal Heirs of Mahmoud Kalbat', OwnerGroup::find($record->owner_group_id)->name);
    }
}
