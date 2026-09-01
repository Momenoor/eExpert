<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards against schema drift — columns that exist in production but that no
 * migration creates.
 *
 * This is the check that was missing when 13 such columns accumulated across
 * parties, courts and users. Because the suite runs migrate:fresh, any column
 * used in code but absent from the migrations fails here immediately. It would
 * have caught that drift the day it appeared: on a clean database, saving a
 * Party threw "Unknown column 'fax'", and since MatterResource reads
 * $user->party (parties.user_id) the Matters page 500'd for every user.
 */
class SchemaFidelityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every column the application writes to must survive a migrate:fresh.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function expectedColumnsProvider(): array
    {
        return [
            'parties' => ['parties', ['id', 'name', 'phone', 'fax', 'address', 'email', 'type', 'role', 'extra', 'parent_id', 'user_id', 'black_list']],
            'courts' => ['courts', ['id', 'name', 'phone', 'email', 'address', 'active']],
            'users' => ['users', ['id', 'name', 'email', 'display_name', 'gender', 'category', 'avatar', 'language', 'font_size']],
            'matters' => ['matters', ['id', 'parent_id', 'year', 'number', 'received_at', 'distributed_at', 'initial_report_at', 'final_report_at', 'final_report_memo_date', 'collection_status', 'difficulty', 'commissioning', 'level', 'is_office_work', 'has_court_penalty', 'has_substantive_changes', 'review_count', 'custom_fields']],
            'matter_requests' => ['matter_requests', ['id', 'matter_id', 'request_by', 'type', 'status', 'comment', 'approved_by', 'approved_at', 'approved_comment', 'email_action', 'extra']],
            'notes' => ['notes', ['id', 'matter_id', 'user_id', 'text', 'datetime']],
            'fees' => ['fees', ['id', 'matter_id', 'user_id', 'type', 'amount', 'date', 'description', 'status']],
            'allocations' => ['allocations', ['id', 'fee_id', 'matter_id', 'user_id', 'amount', 'date', 'description']],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    #[DataProvider('expectedColumnsProvider')]
    public function test_migrations_create_every_column_the_app_uses(string $table, array $columns): void
    {
        $this->assertTrue(Schema::hasTable($table), "Table [{$table}] was not created by the migrations.");

        $actual = Schema::getColumnListing($table);
        $missing = array_values(array_diff($columns, $actual));

        $this->assertSame(
            [],
            $missing,
            "Table [{$table}] is missing column(s) [".implode(', ', $missing).'] after migrate:fresh. '
            .'They exist in production but no migration creates them — add a guarded catch-up migration.'
        );
    }

    public function test_a_party_with_every_used_attribute_can_actually_be_saved(): void
    {
        // The strongest form of the check: not "does the column exist" but "does
        // the write the app actually performs succeed on a fresh database".
        $user = User::factory()->create();

        $party = Party::create([
            'name' => 'Test Party',
            'fax' => '01234',
            'address' => 'Somewhere',
            'extra' => ['note' => 'x'],
            'user_id' => $user->id,
            'role' => ['role' => ['expert'], 'type' => ['assistant']],
        ]);

        $this->assertNotNull($party->fresh());
        $this->assertSame($user->id, $party->fresh()->user_id);
    }

    public function test_a_court_with_every_used_attribute_can_actually_be_saved(): void
    {
        $court = Court::create([
            'name' => 'Test Court',
            'phone' => '0500000000',
            'email' => 'court@example.test',
            'address' => 'Somewhere',
        ]);

        $this->assertSame('court@example.test', $court->fresh()->email);
    }

    public function test_the_matters_query_used_by_the_resource_runs_on_a_fresh_database(): void
    {
        // MatterResource::getEloquentQuery() sorts by COALESCE(parent_id, id) and
        // reads $user->party; both depend on columns that were missing before.
        Matter::create(['number' => '1', 'year' => '2026']);

        $count = Matter::query()
            ->orderByRaw('COALESCE(parent_id, id) ASC, id ASC')
            ->count();

        $this->assertSame(1, $count);
    }
}
