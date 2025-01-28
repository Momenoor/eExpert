<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('court_court_level', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Court::class)->constrained();
            $table->foreignIdFor(\App\Models\CourtLevel::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_court_level');
    }
};
