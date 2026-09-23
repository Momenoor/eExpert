<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_print_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_print_template_page_id')->constrained('lease_print_template_pages')->cascadeOnDelete();
            $table->string('field_key');
            $table->decimal('x_percent', 6, 3);
            $table->decimal('y_percent', 6, 3);
            $table->decimal('width_percent', 6, 3)->nullable();
            $table->decimal('height_percent', 6, 3)->nullable();
            $table->json('column_widths')->nullable();
            $table->json('hidden_columns')->nullable();
            $table->unsignedSmallInteger('font_size')->default(10);
            $table->string('text_align')->default('left');
            $table->boolean('rtl')->default(false);
            $table->string('language', 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_print_template_fields');
    }
};
