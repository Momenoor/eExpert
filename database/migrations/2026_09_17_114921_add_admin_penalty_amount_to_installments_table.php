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
        Schema::table('installments', function (Blueprint $table) {
            if (! Schema::hasColumn('installments', 'admin_penalty_amount')) {
                // Charged when a cheque bounces — added to the balance due
                // rather than replacing it, since the original rent is still
                // owed on top of the penalty for the bounce itself.
                $table->decimal('admin_penalty_amount', 10, 2)->default(0)->after('total_due_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            if (Schema::hasColumn('installments', 'admin_penalty_amount')) {
                $table->dropColumn('admin_penalty_amount');
            }
        });
    }
};
