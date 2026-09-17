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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->decimal('total_base_rent', 12, 2)->default(0);
            $table->decimal('security_deposit_amount', 12, 2)->default(0);
            $table->string('status')->default('draft');

            // Attestation & legal integration (Ejari/Tawtheeq/…)
            $table->string('attestation_system')->nullable();
            $table->string('attestation_serial_number')->nullable();
            $table->string('title_deed_number')->nullable();
            $table->string('attestation_fee_payer')->default('tenant');
            $table->string('attestation_status')->default('unregistered');

            $table->string('dispute_status')->default('none');

            // Set when the office has determined RCM/TOGC (or another) VAT
            // exemption applies — checked before Unit::vatRate() so a
            // contract-level exemption can override what the unit alone
            // would otherwise charge.
            $table->string('tax_exemption_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
