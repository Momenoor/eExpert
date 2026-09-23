<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_group_id')->nullable()->constrained('owner_groups')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('identification_number')->nullable();
            $table->string('nationality')->nullable();
            $table->string('unified_number')->nullable();
            $table->string('trn')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('iban')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_profiles');
    }
};
