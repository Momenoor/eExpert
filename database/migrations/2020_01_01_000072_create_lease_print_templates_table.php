<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type')->default('lease_contract');
            $table->foreignId('owner_group_id')->nullable()->constrained('owner_groups')->cascadeOnDelete();
            $table->string('contract_format')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_print_templates');
    }
};
