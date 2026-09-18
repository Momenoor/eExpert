<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Generalized from "dewa_premise_number" — Sharjah's utility
            // authority is SEWA, not DEWA, but it's the same kind of premise
            // number regardless of emirate.
            if (Schema::hasColumn('units', 'dewa_premise_number') && ! Schema::hasColumn('units', 'premise_number')) {
                $table->renameColumn('dewa_premise_number', 'premise_number');
            }

            if (! Schema::hasColumn('units', 'area_sqm')) {
                $table->decimal('area_sqm', 10, 2)->nullable()->after('rental_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'area_sqm')) {
                $table->dropColumn('area_sqm');
            }

            if (Schema::hasColumn('units', 'premise_number') && ! Schema::hasColumn('units', 'dewa_premise_number')) {
                $table->renameColumn('premise_number', 'dewa_premise_number');
            }
        });
    }
};
