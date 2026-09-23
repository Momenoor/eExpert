<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->nullable();
            $table->json('phone')->nullable();
            $table->string('fax', 191)->nullable();
            $table->text('address')->nullable();
            $table->json('email')->nullable();
            $table->string('type', 191)->default('office');
            $table->json('role')->nullable();
            $table->integer('old_id')->nullable();
            $table->enum('black_list', ['true', 'false'])->default('false');
            $table->text('extra')->nullable();
            // Not a foreign key: added directly against production before any
            // migration tracked this column, and never enforced.
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('assigned user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
