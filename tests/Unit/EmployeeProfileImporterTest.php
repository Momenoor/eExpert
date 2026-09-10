<?php

namespace Tests\Unit;

use App\Filament\Imports\EmployeeProfileImporter;
use App\Models\EmployeeProfile;
use App\Models\Party;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeProfileImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(array $data): EmployeeProfileImporter
    {
        $importer = new EmployeeProfileImporter(new Import, [], []);

        $reflection = new \ReflectionProperty(EmployeeProfileImporter::class, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $data);

        return $importer;
    }

    public function test_it_matches_an_existing_employee_party_by_id(): void
    {
        $party = Party::factory()->employee()->create();

        $record = $this->makeImporter(['party_id' => (string) $party->id])->resolveRecord();

        $this->assertInstanceOf(EmployeeProfile::class, $record);
        $this->assertFalse($record->exists);
        $this->assertSame($party->id, $record->party_id);
    }

    public function test_the_id_match_trims_surrounding_whitespace(): void
    {
        $party = Party::factory()->employee()->create();

        $record = $this->makeImporter(['party_id' => ' '.$party->id.' '])->resolveRecord();

        $this->assertSame($party->id, $record->party_id);
    }

    public function test_re_importing_the_same_employee_updates_the_existing_profile_instead_of_duplicating(): void
    {
        $party = Party::factory()->employee()->create();
        $existing = EmployeeProfile::factory()->for($party)->create();

        $record = $this->makeImporter(['party_id' => (string) $party->id])->resolveRecord();

        $this->assertTrue($record->exists);
        $this->assertSame($existing->id, $record->id);
    }

    public function test_it_fails_gracefully_when_the_party_id_does_not_hold_the_employee_role(): void
    {
        $party = Party::factory()->create();

        $this->expectException(RowImportFailedException::class);

        $this->makeImporter(['party_id' => (string) $party->id])->resolveRecord();
    }

    public function test_it_fails_gracefully_when_the_party_does_not_exist_at_all(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->makeImporter(['party_id' => '999999'])->resolveRecord();
    }

    #[DataProvider('dateFormatProvider')]
    public function test_it_parses_dates_across_the_supported_formats(string $input, string $expected): void
    {
        $party = Party::factory()->employee()->create(['name' => 'Mohammed Ahmed']);

        $reflection = new \ReflectionMethod(EmployeeProfileImporter::class, 'parseDate');
        $reflection->setAccessible(true);

        $this->assertSame($expected, $reflection->invoke(null, $input));

        // Referencing the party keeps this test meaningful if resolveRecord()
        // ever starts depending on date columns.
        $this->assertNotNull($party->id);
    }

    public static function dateFormatProvider(): array
    {
        return [
            'iso' => ['2024-01-15', '2024-01-15'],
            'day/month/year' => ['15/01/2024', '2024-01-15'],
            'day-month-year' => ['15-01-2024', '2024-01-15'],
            'month/day/year' => ['01/15/2024', '2024-01-15'],
            'dot separated' => ['15.01.2024', '2024-01-15'],
        ];
    }

    public function test_a_blank_date_casts_to_null(): void
    {
        $reflection = new \ReflectionMethod(EmployeeProfileImporter::class, 'parseDate');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke(null, null));
        $this->assertNull($reflection->invoke(null, ''));
    }

    public function test_an_unrecognisable_date_throws_a_row_import_failed_exception(): void
    {
        $reflection = new \ReflectionMethod(EmployeeProfileImporter::class, 'parseDate');
        $reflection->setAccessible(true);

        $this->expectException(RowImportFailedException::class);

        $reflection->invoke(null, 'not a date');
    }

    public function test_the_example_download_headers_follow_the_current_locale(): void
    {
        App::setLocale('en');
        $englishColumn = collect(EmployeeProfileImporter::getColumns())->firstOrFail(fn ($column) => $column->getName() === 'employee_no');
        $this->assertSame('Employee Number', $englishColumn->getExampleHeader());

        App::setLocale('ar');
        $arabicColumn = collect(EmployeeProfileImporter::getColumns())->firstOrFail(fn ($column) => $column->getName() === 'employee_no');
        $this->assertSame(__('Employee Number'), $arabicColumn->getExampleHeader());
        $this->assertNotSame('Employee Number', $arabicColumn->getExampleHeader());
    }
}
