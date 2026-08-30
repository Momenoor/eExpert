<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The live column is a native MySQL ENUM('simple','normal','exceptional')
     * — leftover from an earlier terminology — while the app has always
     * written the MatterDifficulty enum's values ('easy'/'medium'/'hard').
     * MySQL silently coerces any non-matching value to '', so this column
     * has been storing blank on every calculation. A plain string removes
     * the mismatch entirely (matches matter_type_incentive_tiers.difficulty,
     * which is already a string with the correct values).
     */
    public function up(): void
    {
        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->string('difficulty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->enum('difficulty', ['simple', 'normal', 'exceptional'])->nullable()->change();
        });
    }
};
