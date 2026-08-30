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
        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->decimal('percentage_override', 5, 2)->nullable()->after('base_percentage')
                ->comment('Manually entered percentage that replaces the computed tier/fixed/committee percentage for this matter.');
        });

        Schema::table('incentive_assistant_extras', function (Blueprint $table) {
            $table->string('fixed_deduction_reason')->nullable()->after('fixed_deduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->dropColumn('percentage_override');
        });

        Schema::table('incentive_assistant_extras', function (Blueprint $table) {
            $table->dropColumn('fixed_deduction_reason');
        });
    }
};
