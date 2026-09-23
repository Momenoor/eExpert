<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('phone', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->enum('active', ['active', 'inactive'])->default('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
