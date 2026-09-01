<?php

use App\Models\LetterTemplate;
use App\Models\Matter;
use App\Models\MatterLetter;
use App\Models\Party;
use App\Models\User;
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
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('Unique identifier for the template, e.g., "welcome-letter"');
            $table->string('subject');
            $table->longText('body');
            $table->json('placeholders')->comment('JSON array of placeholder names and types, e.g., [{"name": "name", "type": "string"}, {"name": "date", "type": "date"}]');
            $table->string('locale')->default('en')->comment('Language locale, e.g., "en", "es", "fr", "de", etc.');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('category')->default('general')->comment('General, legal, etc.');
            $table->timestamps();
        });
        Schema::create('matter_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(LetterTemplate::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Matter::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject')->nullable()->comment('Subject of the letter, if different from the template subject');
            $table->longText('body')->nullable()->comment('Body of the letter, if different from the template body');
            $table->string('status')->default('draft')->comment('Status of the letter, e.g., "draft", "sent", "cancelled", etc.');
            $table->dateTime('sent_at')->nullable()->comment('Timestamp when the letter was sent');
            $table->foreignIdFor(User::class, 'sent_by')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('matter_letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MatterLetter::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Party::class, 'recipient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable()->comment('Email address of the recipient');
            $table->string('name')->nullable()->comment('Name of the recipient');
            $table->string('delivery_status')->default('pending')->comment('Status of the delivery, e.g., "pending", "delivered", "failed", etc.');
            $table->dateTime('delivered_at')->nullable()->comment('Timestamp when the delivery was attempted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('matter_letters');
        Schema::dropIfExists('matter_letter_recipients');
    }
};
