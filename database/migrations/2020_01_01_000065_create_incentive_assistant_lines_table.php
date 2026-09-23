<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_assistant_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_line_id')->constrained('incentive_lines')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->decimal('percentage_override', 5, 2)->nullable()
                ->comment("Manually entered percentage that replaces this specific assistant's computed share for this matter.");
            $table->decimal('share_amount', 12, 2)->comment('net_amount split equally among assistants');
            $table->decimal('extra_percentage', 5, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('minimum_penalty_pct', 5, 2)->default(0)
                ->comment('-2% per matter below minimum 6 in period');
            $table->decimal('minimum_penalty_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->timestamps();

            $table->unique(['incentive_line_id', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_assistant_lines');
    }
};
