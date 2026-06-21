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
        Schema::table('incentive_assistant_extras', function (Blueprint $table) {
            $table->decimal('fixed_deduction', 12, 2)->default(0)->after('penalty_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_assistant_extras', function (Blueprint $table) {
            $table->dropColumn('fixed_deduction');
        });
    }
};
