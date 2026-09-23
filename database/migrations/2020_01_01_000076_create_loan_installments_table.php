<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();

            $table->unsignedTinyInteger('seq')->comment('1 carries the rounding remainder');
            $table->char('due_period', 7)->comment('YYYY-MM, matched against the payroll run');

            $table->decimal('amount', 12, 2);

            $table->foreignId('payslip_id')->nullable()
                ->comment('Set when actually deducted; null = still outstanding')
                ->constrained('payslips')->nullOnDelete();

            $table->timestamps();

            $table->unique(['employee_loan_id', 'seq'], 'li_loan_seq_unique');
            $table->index('due_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
