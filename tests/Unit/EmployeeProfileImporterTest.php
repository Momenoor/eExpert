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

    public function test_it_matches_an_existing_employee_party_by_name(): void
    {
        $party = Party::factory()->employee()->create(['name' => 'Mohammed Ahmed']);

        $record = $this->makeImporter(['party_name' => 'Mohammed Ahmed'])->resolveRecord();

        $this->assertInstanceOf(EmployeeProfile::class, $record);
        $this->assertFalse($record->exists);
        $this->assertSame($party->id, $record->party_id);
    }

    public function test_the_name_match_is_case_insensitive(): void
    {
        $party = Party::factory()->employee()->create(['name' => 'Mohammed Ahmed']);

        $record = $this->makeImporter(['party_name' => 'mohammed ahmed'])->resolveRecord();

        $this->assertSame($party->id, $record->party_id);
    }

    public function test_re_importing_the_same_employee_updates_the_existing_profile_instead_of_duplicating(): void
    {
        $party = Party::factory()->employee()->create(['name' => 'Mohammed Ahmed']);
        $existing = EmployeeProfile::factory()->for($party)->create();

        $record = $this->makeImporter(['party_name' => 'Mohammed Ahmed'])->resolveRecord();

        $this->assertTrue($record->exists);
        $this->assertSame($existing->id, $record->id);
    }

    public function test_it_fails_gracefully_when_no_party_with_that_name_holds_the_employee_role(): void
    {
        Party::factory()->create(['name' => 'Mohammed Ahmed']);

        $this->expectException(RowImportFailedException::class);

        $this->makeImporter(['party_name' => 'Mohammed Ahmed'])->resolveRecord();
    }

    public function test_it_fails_gracefully_when_the_party_does_not_exist_at_all(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->makeImporter(['party_name' => 'Nobody Here'])->resolveRecord();
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
        $englishColumn = collect(EmployeeProfileImporter::getColumns())->firstOrFail(fn ($column) => $column->getName() === 'party_name');
        $this->assertSame('Employee Name', $englishColumn->getExampleHeader());

        App::setLocale('ar');
        $arabicColumn = collect(EmployeeProfileImporter::getColumns())->firstOrFail(fn ($column) => $column->getName() === 'party_name');
        $this->assertSame(__('Employee Name'), $arabicColumn->getExampleHeader());
        $this->assertNotSame('Employee Name', $arabicColumn->getExampleHeader());
    }
}
