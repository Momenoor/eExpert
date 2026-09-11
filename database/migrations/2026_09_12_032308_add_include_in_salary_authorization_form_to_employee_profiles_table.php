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
        Schema::table('employee_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_profiles', 'include_in_salary_authorization_form')) {
                // Defaults true: almost every employee is paid through the same
                // WPS batch, so opting an exception OUT is the common case, not
                // opting everyone IN one at a time.
                $table->boolean('include_in_salary_authorization_form')->default(true)->after('wps_routing_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('employee_profiles', 'include_in_salary_authorization_form')) {
                $table->dropColumn('include_in_salary_authorization_form');
            }
        });
    }
};
