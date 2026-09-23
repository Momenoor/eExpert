<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payslips SNAPSHOT what they were calculated from rather than referencing it.
     * A salary revision, a corrected leave record or a rewritten loan schedule in
     * April must not alter what March paid.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();

            $table->decimal('basic_snapshot', 12, 2)->default(0)
                ->comment('Copied from employee_salary_components at generation');
            $table->decimal('allowances_snapshot', 12, 2)->default(0);
            $table->decimal('incentive_amount', 12, 2)->default(0);
            $table->boolean('incentive_overridden')->default(false)
                ->comment('True once someone edits the imported incentive by hand');

            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('unpaid_days', 5, 1)->default(0)
                ->comment('Σ day_count × (1 − pay_factor) from party_leaves');
            $table->decimal('unpaid_deduction', 12, 2)->default(0);
            $table->decimal('loan_deduction', 12, 2)->default(0);
            $table->decimal('manual_deduction', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);

            $table->decimal('eosg_accrued', 12, 2)->default(0)
                ->comment('Employer cost — not deducted from the employee');

            $table->string('iban_snapshot', 34)->nullable();
            $table->string('bank_name_snapshot')->nullable();

            $table->boolean('needs_review')->default(false);
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->unique(['payroll_run_id', 'party_id'], 'ps_run_party_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
