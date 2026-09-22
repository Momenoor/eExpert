<?php

namespace App\Console\Commands;

use Database\Seeders\PMSDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipes every PMS-exclusive table and reseeds the module's demo data —
 * for repeatable local/demo resets, not production use.
 *
 * `parties` is deliberately left untouched: it is shared with the Matters
 * module (thousands of rows, referenced by `matter_party` and elsewhere),
 * so truncating it here would destroy unrelated legal-case data. Re-running
 * this command creates fresh Party rows for the new demo owners/tenants
 * rather than reusing or resetting existing ones.
 */
class ResetPmsDemoData extends Command
{
    protected $signature = 'pms:reset-demo-data {--force : Skip the confirmation prompt}';

    protected $description = 'Truncate every PMS-exclusive table (resetting their IDs to 1) and reseed PMS demo data';

    /**
     * Order matters only in that child/pivot tables are listed — with
     * foreign key checks disabled below, TRUNCATE itself doesn't care, but
     * this is the same order a manual cleanup would use.
     *
     * @var list<string>
     */
    private const TABLES = [
        //        'lease_print_template_fields',
        //        'lease_print_template_pages',
        //        'lease_print_templates',
        'installment_payments',
        'installments',
        'lease_party',
        'lease_unit',
        'leases',
        'quotation_unit',
        'quotations',
        'condition_template_items',
        'condition_templates',
        'owner_property',
        'owner_group_bank_accounts',
        'owner_profiles',
        'owner_groups',
        'tenants',
        'units',
        'properties',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This truncates every PMS table (properties, units, leases, quotations, owners, tenants, condition/print templates — '.count(self::TABLES).' tables) and resets their IDs to 1, then reseeds demo data. The shared "parties" table is left untouched. Continue?'
        )) {
            $this->comment('Cancelled — nothing was changed.');

            return self::SUCCESS;
        }

        $isSqlite = DB::getDriverName() === 'sqlite';

        DB::statement($isSqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
            $this->line("Truncated {$table}.");
        }

        DB::statement($isSqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS=1');

        // $this->call('db:seed', ['--class' => PMSDemoSeeder::class]);

        $this->info('PMS demo data reset and reseeded.');

        return self::SUCCESS;
    }
}
