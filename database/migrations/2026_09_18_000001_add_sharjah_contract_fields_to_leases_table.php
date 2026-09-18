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
            if (! Schema::hasColumn('leases', 'government_contract_number')) {
                $table->string('government_contract_number')->nullable()->after('quotation_id');
            }
            if (! Schema::hasColumn('leases', 'issue_date')) {
                $table->date('issue_date')->nullable()->after('government_contract_number');
            }
            if (! Schema::hasColumn('leases', 'contract_category')) {
                $table->string('contract_category')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('leases', 'contract_type')) {
                $table->string('contract_type')->nullable()->after('contract_category');
            }
            if (! Schema::hasColumn('leases', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('security_deposit_amount');
            }
            if (! Schema::hasColumn('leases', 'number_of_payments')) {
                $table->unsignedSmallInteger('number_of_payments')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('leases', 'allow_multiple_licenses')) {
                $table->boolean('allow_multiple_licenses')->default(false)->after('number_of_payments');
            }
            if (! Schema::hasColumn('leases', 'annual_rent')) {
                $table->decimal('annual_rent', 12, 2)->nullable()->after('total_base_rent');
            }
            if (! Schema::hasColumn('leases', 'multiple_rent_amount')) {
                $table->decimal('multiple_rent_amount', 12, 2)->nullable()->after('annual_rent');
            }
            if (! Schema::hasColumn('leases', 'designated_use')) {
                $table->string('designated_use')->nullable()->after('multiple_rent_amount');
            }

            // Power of Attorney — whoever signs on behalf of a party under
            // delegated authority, shared shape across every UAE tenancy
            // contract format.
            if (! Schema::hasColumn('leases', 'poa_authority_number')) {
                $table->string('poa_authority_number')->nullable();
            }
            if (! Schema::hasColumn('leases', 'poa_identification_number')) {
                $table->string('poa_identification_number')->nullable();
            }
            if (! Schema::hasColumn('leases', 'poa_unified_number')) {
                $table->string('poa_unified_number')->nullable();
            }
            if (! Schema::hasColumn('leases', 'poa_name')) {
                $table->string('poa_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            foreach ([
                'government_contract_number',
                'issue_date',
                'contract_category',
                'contract_type',
                'payment_method',
                'number_of_payments',
                'allow_multiple_licenses',
                'annual_rent',
                'multiple_rent_amount',
                'designated_use',
                'poa_authority_number',
                'poa_identification_number',
                'poa_unified_number',
                'poa_name',
            ] as $column) {
                if (Schema::hasColumn('leases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
