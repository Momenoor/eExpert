<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_extra_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('min_count');
            $table->unsignedSmallInteger('max_count')->nullable()->comment('null = no upper limit');
            $table->decimal('extra_percentage', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_extra_rules');
    }
};
