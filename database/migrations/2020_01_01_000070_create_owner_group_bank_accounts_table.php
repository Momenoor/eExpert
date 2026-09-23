<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_group_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_group_id')->constrained('owner_groups')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_no')->nullable();
            $table->string('iban')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_group_bank_accounts');
    }
};
