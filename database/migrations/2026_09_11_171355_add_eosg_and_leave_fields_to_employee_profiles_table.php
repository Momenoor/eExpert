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
                if (! Schema::hasColumn('employee_profiles', 'is_eosg_applicable')) {
                    $table->boolean('is_eosg_applicable')->default(true)
                        ->comment('Not every employee accrues gratuity (e.g. certain contract types)');
                }

                if (! Schema::hasColumn('employee_profiles', 'opening_leave_balance')) {
                    $table->decimal('opening_leave_balance', 6, 1)->default(0)
                        ->comment('Days carried over from before this system tracked leave');
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
                if (Schema::hasColumn('employee_profiles', 'is_eosg_applicable')) {
                    $table->dropColumn('is_eosg_applicable');
                }

                if (Schema::hasColumn('employee_profiles', 'opening_leave_balance')) {
                    $table->dropColumn('opening_leave_balance');
                }
            });
        }
    }
};
