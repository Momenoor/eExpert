<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_mail_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('bulk_mail_campaigns')->cascadeOnDelete();
            $table->json('email');
            $table->string('name')->nullable();
            $table->json('placeholders')->nullable();
            $table->json('cc_emails')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('message_id')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->string('unsubscribe_token')->nullable()->unique();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_mail_recipients');
    }
};
