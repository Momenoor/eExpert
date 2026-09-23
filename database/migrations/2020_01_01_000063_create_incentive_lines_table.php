<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_calculation_id')->constrained('incentive_calculations')->cascadeOnDelete();
            $table->foreignId('matter_id')->constrained('matters')->cascadeOnDelete();
            $table->foreignId('fee_id')->nullable()->constrained('fees')->cascadeOnDelete();
            $table->unsignedSmallInteger('completion_days')->nullable();
            $table->string('difficulty')->nullable();
            $table->decimal('fee_amount_excl_vat', 12, 2)->default(0);
            $table->decimal('base_percentage', 5, 2)->default(0)->comment('Matched tier % or fixed %');
            $table->decimal('committee_adjustment', 5, 2)->default(0)
                ->comment('+2 for office committee, -2 for external committee');
            $table->decimal('effective_percentage', 5, 2)->default(0)->comment('base + committee adjustment');
            $table->decimal('base_amount', 12, 2)->default(0)->comment('fee_amount_excl_vat × effective_percentage / 100');
            $table->decimal('review_deduction_pct', 5, 2)->default(0)
                ->comment('-2% first major review, -1% subsequent');
            $table->decimal('final_report_deduction_pct', 5, 2)->default(0)
                ->comment('Late final report deduction');
            $table->decimal('total_deduction_pct', 5, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0)->comment('base_amount after deductions');
            $table->timestamps();

            $table->unique(['incentive_calculation_id', 'fee_id']);
            $table->index(['incentive_calculation_id', 'matter_id'], 'incentive_lines_calculation_matter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_lines');
    }
};
