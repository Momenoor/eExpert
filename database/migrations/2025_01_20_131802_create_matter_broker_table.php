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
        Schema::create('matter_broker', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Matter::class)->constrained();
            $table->foreignIdFor(\App\Models\Broker::class)->constrained();
            $table->string('matter_commission_rate', 10)->default('0.00')->comment('Commission rate for the matter broker');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matter_broker');
    }
};
