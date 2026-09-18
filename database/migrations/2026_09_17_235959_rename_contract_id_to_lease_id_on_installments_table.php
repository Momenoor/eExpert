<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live database still had `contract_id` on `installments` from before
 * this app's "Contract" terminology was renamed to "Lease" — the migration
 * file and the `Installment` model were updated at the time, but the
 * already-applied `create_installments_table` migration was never re-run
 * (editing a migration file doesn't retroactively change an already-Ran
 * migration), so the column drifted out of sync with the code. This brings
 * the schema back in line with what `Installment::lease()` actually expects.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            if (Schema::hasColumn('installments', 'contract_id') && ! Schema::hasColumn('installments', 'lease_id')) {
                $table->renameColumn('contract_id', 'lease_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            if (Schema::hasColumn('installments', 'lease_id') && ! Schema::hasColumn('installments', 'contract_id')) {
                $table->renameColumn('lease_id', 'contract_id');
            }
        });
    }
};
