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
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            // due_date + the contract's grace_period_days, stamped at
            // generation time so the overdue flagger never has to re-derive
            // it (and a later change to the contract's own grace period
            // can't silently move a due date already sent to the tenant).
            $table->date('grace_period_expiry_date');

            // Financial breakdown
            $table->decimal('net_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_due_amount', 12, 2);

            // Payment
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('transaction_reference')->nullable();
            $table->date('paid_date')->nullable();

            // Tax invoice compliance
            $table->string('landlord_trn')->nullable();
            $table->string('tenant_trn')->nullable();
            $table->string('tax_invoice_serial')->nullable();
            $table->date('date_of_supply')->nullable();
            $table->decimal('vat_rate', 6, 4)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
