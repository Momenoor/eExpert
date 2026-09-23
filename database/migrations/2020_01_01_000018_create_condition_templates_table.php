<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condition_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('emirate')->nullable();
            $table->string('contract_format');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condition_templates');
    }
};
