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
        // Backs the Matter "Notes" feature (App\Models\Note) — used throughout
        // the app but never had a table-creation migration, only ever added
        // directly against the live DB.
        if (! Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->longText('text')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
