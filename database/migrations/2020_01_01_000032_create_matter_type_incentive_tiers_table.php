<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_type_incentive_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('matter_type_incentive_configs')->cascadeOnDelete();
            $table->string('difficulty');
            $table->unsignedSmallInteger('days_from');
            $table->unsignedSmallInteger('days_to')->nullable()->comment('null = no upper limit');
            $table->decimal('percentage', 5, 2)->comment('Base incentive % of fee amount excl. VAT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_type_incentive_tiers');
    }
};
