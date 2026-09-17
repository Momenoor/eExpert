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
        Schema::create('quotation_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            // The negotiated figure for this unit on this quote — not
            // necessarily the unit's own on-file rental_rate.
            $table->decimal('offered_rent', 12, 2);
            // Frozen at generation time from Unit::vatRate() as of that
            // moment, so a later change to the unit's classification can't
            // silently rewrite a quote already sent to the tenant.
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['quotation_id', 'unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_unit');
    }
};
