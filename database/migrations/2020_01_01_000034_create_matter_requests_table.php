<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('matter_id');
            $table->foreignId('request_by')->constrained('users');
            $table->string('status', 191)->default('pending');
            $table->longText('comment')->comment('Comment for the matter request');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->longText('approved_comment')->nullable()->comment('Comment for the matter request approval');
            $table->string('email_action')->nullable();
            $table->json('extra')->nullable();
            $table->string('type', 191);
            $table->timestamps();

            $table->index('matter_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_requests');
    }
};
