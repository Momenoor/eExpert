<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_assistant_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_calculation_id')->constrained('incentive_calculations')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->unsignedSmallInteger('completed_matter_count');
            $table->boolean('meets_minimum')->default(true)->comment('false if completed < 6 matters');
            $table->decimal('minimum_penalty_pct', 5, 2)->default(0)
                ->comment('2% × (6 - completed_count) if below minimum');
            $table->decimal('extra_percentage', 5, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->decimal('fixed_deduction', 12, 2)->default(0);
            $table->string('fixed_deduction_reason')->nullable();
            $table->timestamps();

            $table->unique(['incentive_calculation_id', 'party_id'], 'inc_asst_extras_calc_party_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_assistant_extras');
    }
};
