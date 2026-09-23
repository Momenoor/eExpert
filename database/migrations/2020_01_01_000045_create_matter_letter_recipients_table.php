<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_letter_id')->nullable()->constrained('matter_letters')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('parties')->cascadeOnDelete();
            $table->string('email')->nullable()->comment('Email address of the recipient');
            $table->string('name')->nullable()->comment('Name of the recipient');
            $table->string('delivery_status')->default('pending')->comment('Status of the delivery, e.g., "pending", "delivered", "failed", etc.');
            $table->dateTime('delivered_at')->nullable()->comment('Timestamp when the delivery was attempted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_letter_recipients');
    }
};
