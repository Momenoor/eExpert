<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The same "Contract" → "Lease" rename that left `installments.contract_id`
 * behind (see 2026_09_17_235959_rename_contract_id_to_lease_id_on_installments_table)
 * left more debris: the column itself was renamed to `lease_id`, but its
 * foreign key constraint still targeted the old `contracts` table — and
 * `contracts`, `contract_party`, `contract_unit` were never dropped once
 * `leases`, `lease_party`, `lease_unit` replaced them. All three old tables
 * are confirmed empty and nothing but this stale FK references them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('installments') && Schema::hasTable('leases')) {
            $foreignKey = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments'
                 AND COLUMN_NAME = 'lease_id' AND REFERENCED_TABLE_NAME = 'contracts'",
            ))->first();

            if ($foreignKey !== null) {
                Schema::table('installments', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                    $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();
                });
            }
        }

        foreach (['contract_party', 'contract_unit', 'contracts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() === 0) {
                Schema::dropIfExists($table);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_party')) {
            Schema::create('contract_party', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('contract_unit')) {
            Schema::create('contract_unit', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('installments')) {
            $foreignKey = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments'
                 AND COLUMN_NAME = 'lease_id' AND REFERENCED_TABLE_NAME = 'leases'",
            ))->first();

            if ($foreignKey !== null) {
                Schema::table('installments', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                    $table->foreign('lease_id')->references('id')->on('contracts')->cascadeOnDelete();
                });
            }
        }
    }
};
