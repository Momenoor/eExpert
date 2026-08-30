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
        if (! Schema::hasTable('matter_type_incentive_tiers')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            // The application (MatterDifficulty enum) reads/writes 'easy' | 'medium' | 'hard'.
            // Normalize any legacy values from the original ['simple','normal','exceptional'] enum.
            DB::statement("UPDATE matter_type_incentive_tiers SET difficulty = 'easy' WHERE difficulty = 'simple'");
            DB::statement("UPDATE matter_type_incentive_tiers SET difficulty = 'medium' WHERE difficulty = 'normal'");
            DB::statement("UPDATE matter_type_incentive_tiers SET difficulty = 'hard' WHERE difficulty = 'exceptional'");
        }

        Schema::table('matter_type_incentive_tiers', function (Blueprint $table) {
            $table->string('difficulty')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('matter_type_incentive_tiers')) {
            return;
        }

        Schema::table('matter_type_incentive_tiers', function (Blueprint $table) {
            $table->enum('difficulty', ['simple', 'normal', 'exceptional'])->change();
        });
    }
};
