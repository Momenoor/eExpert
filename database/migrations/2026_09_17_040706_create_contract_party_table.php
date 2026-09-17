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
        Schema::create('contract_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            // primary_tenant | co_tenant | guarantor
            $table->string('role');
            // A guarantor/co-tenant's own contract_party.id, so a guarantor
            // can be shown as "guaranteeing" a specific tenant rather than
            // just the contract in general — mirrors matter_party's own
            // parent_id/representative pattern.
            $table->foreignId('parent_id')->nullable()->constrained('contract_party')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_party');
    }
};
