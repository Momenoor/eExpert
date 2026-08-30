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
        if (Schema::hasTable('types') && ! Schema::hasColumn('types', 'incentive_config_id')) {
            Schema::table('types', function (Blueprint $table) {
                $table->foreignId('incentive_config_id')->nullable()->constrained('matter_type_incentive_configs')->nullOnDelete();
            });
        }

        if (Schema::hasTable('matter_type_incentive_configs') && Schema::hasColumn('matter_type_incentive_configs', 'type_id')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::disableForeignKeyConstraints();
                Schema::table('matter_type_incentive_configs', function (Blueprint $table) {
                    $table->dropColumn('type_id');
                });
                Schema::enableForeignKeyConstraints();
            } else {
                Schema::table('matter_type_incentive_configs', function (Blueprint $table) {
                    if (DB::getDriverName() === 'mysql') {
                        $table->dropForeign(['type_id']);
                    }
                    $table->dropUnique(['type_id']);
                    $table->dropColumn('type_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('matter_type_incentive_configs') && ! Schema::hasColumn('matter_type_incentive_configs', 'type_id')) {
            Schema::table('matter_type_incentive_configs', function (Blueprint $table) {
                $table->foreignId('type_id')->nullable()->constrained('types')->nullOnDelete();
            });
        }

        if (Schema::hasTable('types') && Schema::hasColumn('types', 'incentive_config_id')) {
            Schema::table('types', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    $table->dropForeign(['incentive_config_id']);
                }
                $table->dropColumn('incentive_config_id');
            });
        }
    }
};
