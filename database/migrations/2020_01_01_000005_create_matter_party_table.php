<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_party', function (Blueprint $table) {
            $table->id();
            // Neither matter_id nor party_id is a foreign key: never enforced
            // by any migration.
            $table->unsignedBigInteger('matter_id');
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('role', 255)->nullable();
            $table->string('type', 191)->default('plaintiff');
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->index('matter_id');
            $table->index('party_id');
            $table->index(['role', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_party');
    }
};
