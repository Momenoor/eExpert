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
        Schema::create('owner_groups', function (Blueprint $table) {
            $table->id();
            // e.g. "Legal Heirs of Mahmoud Kalbat" — a collective name a
            // contract's landlord line shows instead of listing every heir
            // individually, once ownership has passed to more than one
            // person but is still administered as a single estate.
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_groups');
    }
};
