<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('renewed_from_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->string('government_contract_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('contract_category')->nullable();
            $table->string('contract_type')->nullable();
            $table->foreignId('condition_template_id')->nullable()
                ->constrained('condition_templates')->nullOnDelete();
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->decimal('total_base_rent', 12, 2)->default(0);
            $table->decimal('annual_rent', 12, 2)->nullable();
            $table->unsignedSmallInteger('number_of_occupants')->nullable();
            $table->decimal('security_deposit_amount', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->unsignedSmallInteger('number_of_payments')->nullable();
            $table->boolean('allow_multiple_licenses')->default(false);
            $table->string('status')->default('draft');
            $table->string('attestation_system')->nullable();
            $table->string('attestation_serial_number')->nullable();
            $table->string('title_deed_number')->nullable();
            $table->string('attestation_fee_payer')->default('tenant');
            $table->string('attestation_status')->default('unregistered');
            $table->string('dispute_status')->default('none');
            $table->string('tax_exemption_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('poa_authority_number')->nullable();
            $table->string('poa_identification_number')->nullable();
            $table->string('poa_unified_number')->nullable();
            $table->string('poa_name')->nullable();
            $table->string('multiple_rent_amount', 3)->default('no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
