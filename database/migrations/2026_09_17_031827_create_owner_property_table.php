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
        Schema::create('owner_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // How much of the property this owner holds — sums to 100 across a
            // property's owners, enforced at the form level (a DB constraint
            // can't see sibling rows at insert time).
            $table->decimal('ownership_percentage', 5, 2);
            $table->timestamps();

            $table->unique(['owner_party_id', 'property_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_property');
    }
};
