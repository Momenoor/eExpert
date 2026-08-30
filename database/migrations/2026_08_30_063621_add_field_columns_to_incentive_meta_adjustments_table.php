<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These columns already exist on the live database (added outside the
     * migration system) but were never captured in a migration, so a fresh
     * install/test run was missing them entirely.
     */
    public function up(): void
    {
        Schema::table('incentive_meta_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('incentive_meta_adjustments', 'field_name')) {
                $table->string('field_name');
            }
            if (! Schema::hasColumn('incentive_meta_adjustments', 'field_value')) {
                $table->string('field_value');
            }
            if (! Schema::hasColumn('incentive_meta_adjustments', 'percentage_adjustment')) {
                $table->float('percentage_adjustment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_meta_adjustments', function (Blueprint $table) {
            $table->dropColumn(['field_name', 'field_value', 'percentage_adjustment']);
        });
    }
};
