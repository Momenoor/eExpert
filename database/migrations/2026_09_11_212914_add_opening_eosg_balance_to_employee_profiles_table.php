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
                if (! Schema::hasColumn('employee_profiles', 'opening_eosg_balance')) {
                    $table->decimal('opening_eosg_balance', 12, 2)->default(0)
                        ->comment('Gratuity (AED) entered once by HR/Finance for service before this system tracked it; added to whichever closing voucher is generated first for the employee');
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
                if (Schema::hasColumn('employee_profiles', 'opening_eosg_balance')) {
                    $table->dropColumn('opening_eosg_balance');
                }
            });
        }
    }
};
