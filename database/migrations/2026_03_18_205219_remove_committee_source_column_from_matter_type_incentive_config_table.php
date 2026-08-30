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
        if (Schema::hasTable('matter_type_incentive_configs') && Schema::hasColumn('matter_type_incentive_configs', 'committee_source')) {
            Schema::table('matter_type_incentive_configs', function (Blueprint $table) {
                $table->dropColumn('committee_source');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matter_type_incentive_configs') && ! Schema::hasColumn('matter_type_incentive_configs', 'committee_source')) {
            Schema::table('matter_type_incentive_configs', function (Blueprint $table) {
                $table->enum('committee_source', ['office', 'external'])->nullable()
                    ->comment('office = +2%, external = -2% from tiered base');
            });
        }
    }
};
