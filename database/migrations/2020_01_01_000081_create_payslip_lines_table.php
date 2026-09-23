<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();

            $table->string('kind', 16)->comment('earning, deduction, employer_cost');
            $table->string('label');
            $table->decimal('amount', 12, 2);

            $table->string('gl_account')->nullable();

            $table->timestamps();

            $table->index(['payslip_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
    }
};
