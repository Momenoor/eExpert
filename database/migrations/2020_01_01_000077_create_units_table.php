<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('unit_number');
            $table->string('floor')->nullable();
            $table->decimal('rental_rate', 12, 2)->default(0);
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->unsignedSmallInteger('number_of_rooms')->nullable();
            $table->string('premise_number')->nullable();
            $table->string('property_classification');
            $table->string('unit_type');
            $table->string('status')->default('vacant');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
