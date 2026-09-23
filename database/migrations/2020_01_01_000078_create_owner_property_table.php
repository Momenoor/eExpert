<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->decimal('ownership_percentage', 5, 2);
            $table->timestamps();

            $table->unique(['owner_party_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_property');
    }
};
