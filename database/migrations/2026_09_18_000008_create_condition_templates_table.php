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
        Schema::create('condition_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('emirate')->nullable();
            // sharjah_commercial | sharjah_residential | dubai_ejari — which
            // print view knows how to render this template's sections.
            $table->string('contract_format');
            $table->timestamps();
        });

        Schema::create('condition_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_template_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('text_en');
            $table->text('text_ar');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condition_template_items');
        Schema::dropIfExists('condition_templates');
    }
};
