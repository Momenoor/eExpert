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
        Schema::create('lease_print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // sharjah_commercial | sharjah_residential | dubai_ejari
            $table->string('contract_format')->unique();
            $table->timestamps();
        });

        Schema::create('lease_print_template_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_print_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('background_image_path')->nullable();
            // The uploaded image's natural pixel dimensions — field
            // positions are stored as percentages of these, so the same
            // coordinates render correctly regardless of on-screen or
            // print scale.
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->timestamps();

            $table->unique(['lease_print_template_id', 'page_number'], 'lease_print_template_pages_template_page_unique');
        });

        Schema::create('lease_print_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_print_template_page_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->decimal('x_percent', 6, 3);
            $table->decimal('y_percent', 6, 3);
            $table->unsignedSmallInteger('font_size')->default(10);
            $table->string('text_align')->default('left');
            // Whether this field's own VALUE is Arabic text (e.g. a name
            // typed in Arabic) — most fields are plain numbers/Latin text
            // since the labels are already printed on the background image.
            $table->boolean('rtl')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_print_template_fields');
        Schema::dropIfExists('lease_print_template_pages');
        Schema::dropIfExists('lease_print_templates');
    }
};
