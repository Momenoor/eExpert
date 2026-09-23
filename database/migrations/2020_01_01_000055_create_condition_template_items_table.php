<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condition_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_template_id')->constrained('condition_templates')->cascadeOnDelete();
            $table->string('section');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('text_en');
            $table->text('text_ar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condition_template_items');
    }
};
