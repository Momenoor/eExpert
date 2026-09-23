<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_template_id')->nullable()->constrained('letter_templates')->cascadeOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained('matters')->cascadeOnDelete();
            $table->string('subject')->nullable()->comment('Subject of the letter, if different from the template subject');
            $table->longText('body')->nullable()->comment('Body of the letter, if different from the template body');
            $table->string('status')->default('draft')->comment('Status of the letter, e.g., "draft", "sent", "cancelled", etc.');
            $table->dateTime('sent_at')->nullable()->comment('Timestamp when the letter was sent');
            $table->foreignId('sent_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_letters');
    }
};
