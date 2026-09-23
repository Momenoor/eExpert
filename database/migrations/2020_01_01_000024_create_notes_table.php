<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backs the Matter "Notes" feature (App\Models\Note) — never had a
        // FK-enforced relation; matter_id/user_id were added directly
        // against the live database and never constrained.
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('matter_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->longText('text')->nullable();
            $table->dateTime('datetime')->nullable();
            $table->timestamps();

            $table->index('matter_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
