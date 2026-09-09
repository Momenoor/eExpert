<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loans, payroll runs, payslips and gratuity accrual.
 *
 * Payslips SNAPSHOT what they were calculated from rather than referencing it.
 * A salary revision, a corrected leave record or a rewritten loan schedule in
 * April must not alter what March paid — the same reason a finalised
 * IncentiveCalculation keeps its own stored figures instead of re-reading live
 * configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_loans')) {
            Schema::create('employee_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();

                $table->string('kind', 16)->default('loan')->comment('loan, petty_cash');
                $table->decimal('principal', 12, 2)->comment('A in the schedule formula');
                $table->unsignedTinyInteger('months')->comment('N in the schedule formula');
                $table->date('starts_on')->comment('First instalment period');

                $table->string('status', 16)->default('active')
                    ->comment('active, settled, written_off');
                $table->text('notes')->nullable();

                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['party_id', 'status']);
            });
        }

        if (! Schema::hasTable('loan_installments')) {
            Schema::create('loan_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();

                $table->unsignedTinyInteger('seq')->comment('1 carries the rounding remainder');
                $table->char('due_period', 7)->comment('YYYY-MM, matched against the payroll run');

                // Frozen when the schedule is generated. Recomputing monthly would
                // let a later edit silently shift every remaining instalment.
                $table->decimal('amount', 12, 2);

                $table->foreignId('payslip_id')->nullable()
                    ->comment('Set when actually deducted; null = still outstanding');

                $table->timestamps();

                $table->unique(['employee_loan_id', 'seq'], 'li_loan_seq_unique');
                $table->index('due_period');
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->char('period', 7)->unique()->comment('YYYY-MM; one run per month');
                $table->string('name')->nullable();

                $table->string('status', 24)->default('draft')->index()
                    ->comment('draft, hr_review, finance_approval, approved, disbursed');

                // Two distinct approvers recorded separately: "HR checked the
                // days" and "Finance released the money" are different assertions.
                $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('hr_approved_at')->nullable();
                $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finance_approved_at')->nullable();
                $table->timestamp('disbursed_at')->nullable();

                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();

                $table->decimal('basic_snapshot', 12, 2)->default(0)
                    ->comment('Copied from employee_salary_components at generation');
                $table->decimal('allowances_snapshot', 12, 2)->default(0);
                $table->decimal('incentive_amount', 12, 2)->default(0);

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

                // What the transfer actually used, even if the profile changes later.
                $table->string('iban_snapshot', 34)->nullable();
                $table->string('bank_name_snapshot')->nullable();

                // Deductions exceeding gross are surfaced for a human decision
                // rather than clamped to zero, which would lose the balance.
                $table->boolean('needs_review')->default(false);
                $table->text('review_note')->nullable();

                $table->timestamps();

                $table->unique(['payroll_run_id', 'party_id'], 'ps_run_party_unique');
            });
        }

        if (! Schema::hasTable('payslip_lines')) {
            Schema::create('payslip_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();

                $table->string('kind', 16)->comment('earning, deduction, employer_cost');
                $table->string('label');
                $table->decimal('amount', 12, 2);

                // Makes the journal voucher a GROUP BY rather than a special
                // report: the same line data drives the payslip and the voucher.
                $table->string('gl_account')->nullable();

                $table->timestamps();

                $table->index(['payslip_id', 'kind']);
            });
        }

        if (! Schema::hasTable('eosg_accruals')) {
            Schema::create('eosg_accruals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();
                $table->char('period', 7);

                // Stored rather than derived from the joining date, because days
                // of unpaid leave do not accrue service under Article 51.
                $table->unsignedInteger('service_days')->default(0);
                $table->decimal('basic_snapshot', 12, 2)->default(0);
                $table->decimal('accrued_this_month', 12, 2)->default(0);
                $table->decimal('cumulative_liability', 12, 2)->default(0)
                    ->comment('What settlement would cost today, after the two-year cap');

                $table->timestamps();

                $table->unique(['party_id', 'period'], 'eosg_party_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eosg_accruals');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('employee_loans');
    }
};
