<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a payslip whose incentive was set by hand.
 *
 * The incentive is now imported from the finalised incentive calculation whose
 * period ends inside the payroll month, so regenerating a run re-derives it —
 * which is right, and would silently throw away a figure someone deliberately
 * corrected. This flag is how generation tells the two apart: an untouched
 * payslip re-imports, an overridden one keeps what a person typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payslips') && ! Schema::hasColumn('payslips', 'incentive_overridden')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->boolean('incentive_overridden')->default(false)->after('incentive_amount')
                    ->comment('True once someone edits the imported incentive by hand');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payslips') && Schema::hasColumn('payslips', 'incentive_overridden')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->dropColumn('incentive_overridden');
            });
        }
    }
};
