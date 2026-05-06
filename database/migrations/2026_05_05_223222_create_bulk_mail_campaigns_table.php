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
        Schema::create('bulk_mail_campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->string('from_sender_key');
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->boolean('has_attachment')->default(false);
            $table->json('attachment_path')->nullable();
            $table->string('attachment_disk')->default('local');
            $table->integer('daily_send_limit')->default(50);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft');
            $table->json('placeholders')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_mail_campaigns');
    }
};
