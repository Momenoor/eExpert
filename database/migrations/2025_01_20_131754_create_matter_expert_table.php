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
        Schema::create('matter_expert', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Matter::class)->constrained();
            $table->foreignIdFor(\App\Models\Expert::class)->constrained();
            $table->foreignIdFor(\App\Models\ExpertType::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matter_expert');
    }
};
