<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_line_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_line_id')->constrained('incentive_lines')->cascadeOnDelete();
            $table->enum('type', ['review_first', 'review_subsequent', 'late_final_report', 'court_penalty']);
            $table->decimal('percentage', 5, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_line_deductions');
    }
};
