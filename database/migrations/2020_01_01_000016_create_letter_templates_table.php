<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
