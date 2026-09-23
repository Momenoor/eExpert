<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_security_deposit')->default(false);
            $table->boolean('is_vat_only')->default(false);
            $table->date('due_date');
            $table->date('grace_period_expiry_date');

            $table->decimal('net_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_due_amount', 12, 2);
            $table->decimal('admin_penalty_amount', 10, 2)->default(0);

            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('transaction_reference')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('paid_date')->nullable();

            $table->string('landlord_trn')->nullable();
            $table->string('tenant_trn')->nullable();
            $table->string('tax_invoice_serial')->nullable();
            $table->date('date_of_supply')->nullable();
            $table->decimal('vat_rate', 6, 4)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
