<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('offered_rent', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['quotation_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_unit');
    }
};
