<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('role')->comment('primary_tenant, co_tenant, guarantor');
            $table->foreignId('parent_id')->nullable()->constrained('lease_party')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_party');
    }
};
