<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A finished matter with no fee at all still needs to be importable
        // (so it counts toward the monthly achievement quota), so its
        // incentive line has no fee to reference.
        Schema::table('incentive_lines', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_NAME = 'incentive_lines' AND COLUMN_NAME = 'fee_id' AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = DATABASE()"
                );
                foreach ($foreignKeys as $fk) {
                    $table->dropForeign($fk->CONSTRAINT_NAME);
                }
            }
            $table->unsignedBigInteger('fee_id')->nullable()->change();
        });

        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->foreign('fee_id')->references('id')->on('fees')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_lines', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_NAME = 'incentive_lines' AND COLUMN_NAME = 'fee_id' AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = DATABASE()"
                );
                foreach ($foreignKeys as $fk) {
                    $table->dropForeign($fk->CONSTRAINT_NAME);
                }
            }
            $table->unsignedBigInteger('fee_id')->nullable(false)->change();
        });

        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->foreign('fee_id')->references('id')->on('fees')->cascadeOnDelete();
        });
    }
};
