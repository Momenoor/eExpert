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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            // The prospective tenant — a Party in the 'tenant' role, whether
            // or not they end up signing anything.
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->decimal('base_rent', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            // A flat estimate, not the real government fee — the real one
            // depends on the emirate and system chosen at contract stage.
            $table->decimal('attestation_fee_estimate', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('security_deposit', 12, 2)->default(0);
            $table->unsignedTinyInteger('number_of_installments')->default(1);
            $table->date('validity_date');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
