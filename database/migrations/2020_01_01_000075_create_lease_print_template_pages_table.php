<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_print_template_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_print_template_id')->constrained('lease_print_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('background_image_path')->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->timestamps();

            $table->unique(['lease_print_template_id', 'page_number'], 'lease_print_template_pages_template_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_print_template_pages');
    }
};
