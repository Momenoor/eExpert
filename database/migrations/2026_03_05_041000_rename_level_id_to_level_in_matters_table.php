<?php

use App\Enums\MatterLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('matters')) {
            // 1. Drop the old level column if level_id exists to be renamed
            if (Schema::hasColumn('matters', 'level_id')) {
                Schema::table('matters', function (Blueprint $table) {
                    if (Schema::hasColumn('matters', 'level')) {
                        $table->dropColumn('level');
                    }
                });

                // 2. Rename level_id to level
                Schema::table('matters', function (Blueprint $table) {
                    $table->renameColumn('level_id', 'level');
                });
            }

            // 3. Update numeric values to enum strings
            if (Schema::hasColumn('matters', 'level')) {
                DB::table('matters')->where('level', '1')->update(['level' => MatterLevel::FIRST_INSTANCE]);
                DB::table('matters')->where('level', '2')->update(['level' => MatterLevel::APPEAL]);
                DB::table('matters')->where('level', '3')->update(['level' => MatterLevel::CONGESTION]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                if (Schema::hasColumn('matters', 'level')) {
                    $table->renameColumn('level', 'level_id');
                }
                $table->string('level')->nullable();
            });
        }
    }
};
