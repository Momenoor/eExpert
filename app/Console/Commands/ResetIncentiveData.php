<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('incentive:reset')]
#[Description('Reset all incentive related tables data.')]
class ResetIncentiveData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (! $this->option('no-interaction') && ! $this->confirm('Are you sure you want to reset all incentive data? This cannot be undone.')) {
            $this->info('Aborted.');

            return;
        }

        Schema::disableForeignKeyConstraints();

        $tables = [
            'matter_type_incentive_config_type',
            'matter_type_incentive_tiers',
            'matter_type_incentive_configs',
            'incentive_calculations',
            'incentive_extra_rules',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // Also clear the config_id on types table
        if (Schema::hasColumn('types', 'incentive_config_id')) {
            DB::table('types')->update(['incentive_config_id' => null]);
        }

        Schema::enableForeignKeyConstraints();

        $this->info('Incentive data reset successfully.');
    }
}
