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
        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->string('year');
            $table->string('number');
            $table->date('received_at');
            $table->date('reported_at')->nullable();
            $table->date('submitted_at')->nullable();
            $table->date('next_session_at');
            $table->date('last_action_at')->nullable();
            $table->foreignIdFor(\App\Models\Court::class)->constrained();
            $table->foreignIdFor(\App\Models\CourtLevel::class)->constrained();
            $table->foreignIdFor(\App\Models\MatterStatus::class)->constrained();
            $table->foreignIdFor(\App\Models\MatterType::class)->constrained();
            $table->foreignIdFor(\App\Models\Matter::class, 'parent_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
