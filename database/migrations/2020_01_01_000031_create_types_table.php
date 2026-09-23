<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->boolean('active')->default(true);
            $table->string('incentive_trigger_type')->default('final_report_date')->comment('final_report_date or fees_collected_date');
            $table->timestamps();
            $table->foreignId('incentive_config_id')->nullable()->constrained('matter_type_incentive_configs')->nullOnDelete();
            $table->boolean('allow_current_status_import')->default(false);
            $table->boolean('exclude_from_incentive_count')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types');
    }
};
