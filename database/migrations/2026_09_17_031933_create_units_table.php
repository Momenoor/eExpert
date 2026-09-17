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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number');
            $table->string('floor')->nullable();
            $table->decimal('rental_rate', 12, 2)->default(0);
            $table->string('dewa_premise_number')->nullable();
            $table->string('property_classification');
            $table->string('unit_type');
            $table->string('status')->default('vacant');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['building_id', 'unit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
