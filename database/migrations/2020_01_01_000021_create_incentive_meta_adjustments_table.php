<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_meta_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('field_name');
            $table->string('field_value');
            $table->float('percentage_adjustment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_meta_adjustments');
    }
};
