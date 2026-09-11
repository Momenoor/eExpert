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
        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_profiles', 'eosg_paid_amount')) {
                    $table->decimal('eosg_paid_amount', 12, 2)->default(0)
                        ->comment('Cumulative gratuity actually paid out to the employee, entered by HR/Finance');
                }

                if (! Schema::hasColumn('employee_profiles', 'eosg_paid_at')) {
                    $table->date('eosg_paid_at')->nullable()
                        ->comment('Date of the most recent gratuity payment, if any');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('employee_profiles', 'eosg_paid_amount')) {
                    $table->dropColumn('eosg_paid_amount');
                }

                if (Schema::hasColumn('employee_profiles', 'eosg_paid_at')) {
                    $table->dropColumn('eosg_paid_at');
                }
            });
        }
    }
};
