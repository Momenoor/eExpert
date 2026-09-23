<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eosg_closing_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eosg_closing_voucher_id')->constrained('eosg_closing_vouchers')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['eosg_closing_voucher_id', 'party_id'], 'eosg_closing_voucher_lines_voucher_party_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eosg_closing_voucher_lines');
    }
};
