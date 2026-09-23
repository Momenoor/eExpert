<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_type_incentive_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('calculation_type', ['tiered', 'fixed', 'committee']);
            $table->decimal('fixed_percentage', 5, 2)->nullable()->comment('Used when calculation_type = fixed (e.g. 8 for 8%)');
            $table->decimal('assistant_rate', 5, 2)->default(100)->comment('% of base incentive allocated to assistants collectively');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_type_incentive_configs');
    }
};
