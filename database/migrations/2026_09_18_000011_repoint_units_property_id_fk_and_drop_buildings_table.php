<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Another artifact of the same "Building" → "Property" rename that left
 * `installments.contract_id` behind (see
 * 2026_09_17_235959_rename_contract_id_to_lease_id_on_installments_table):
 * `units.property_id`'s foreign key still pointed at the old, empty
 * `buildings` table instead of `properties`, and the original `buildings`
 * table itself was never dropped once `properties` replaced it. Both are
 * fixed here.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The stale FK this fixes can only exist on the live MySQL database
        // that predates the Building→Property rename — a fresh SQLite test
        // database already gets the correct FK straight from
        // `create_units_table`'s own `constrained()` call, and
        // `information_schema` isn't a SQLite concept at all.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('units') && Schema::hasTable('properties')) {
            $foreignKey = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units'
                 AND COLUMN_NAME = 'property_id' AND REFERENCED_TABLE_NAME = 'buildings'",
            ))->first();

            if ($foreignKey !== null) {
                Schema::table('units', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                    $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('buildings') && DB::table('buildings')->count() === 0) {
            Schema::dropIfExists('buildings');
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

        if (! Schema::hasTable('buildings')) {
            Schema::create('buildings', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->unsignedInteger('total_units')->nullable();
                $table->unsignedSmallInteger('year_built')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('units')) {
            $foreignKey = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units'
                 AND COLUMN_NAME = 'property_id' AND REFERENCED_TABLE_NAME = 'properties'",
            ))->first();

            if ($foreignKey !== null) {
                Schema::table('units', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                    $table->foreign('property_id')->references('id')->on('buildings')->cascadeOnDelete();
                });
            }
        }
    }
};
