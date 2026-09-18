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
        Schema::table('leases', function (Blueprint $table) {
            if (! Schema::hasColumn('leases', 'renewed_from_lease_id')) {
                $table->foreignId('renewed_from_lease_id')->nullable()
                    ->after('quotation_id')
                    ->constrained('leases')->nullOnDelete();
            }

            if (! Schema::hasColumn('leases', 'condition_template_id')) {
                $table->foreignId('condition_template_id')->nullable()
                    ->after('contract_type')
                    ->constrained()->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'condition_template_id')) {
                $table->dropConstrainedForeignId('condition_template_id');
            }

            if (Schema::hasColumn('leases', 'renewed_from_lease_id')) {
                $table->dropConstrainedForeignId('renewed_from_lease_id');
            }
        });
    }
};
