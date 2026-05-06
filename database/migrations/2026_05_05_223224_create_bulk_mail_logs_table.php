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
        Schema::create('bulk_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('bulk_mail_campaigns')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('bulk_mail_recipients')->cascadeOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_mail_logs');
    }
};
