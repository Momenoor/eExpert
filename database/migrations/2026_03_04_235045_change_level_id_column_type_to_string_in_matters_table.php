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
        if (Schema::hasTable('matters')) {
            if (DB::getDriverName() === 'mysql') {
                // Try to disable strict mode temporarily for the session to fix bad data
                DB::statement("SET SESSION sql_mode = ''");

                // Fix invalid dates that block column changes
                DB::statement("UPDATE matters SET received_date = NULL WHERE CAST(received_date AS CHAR) = '0000-00-00'");
                DB::statement("UPDATE matters SET next_session_date = NULL WHERE CAST(next_session_date AS CHAR) = '0000-00-00'");
                DB::statement("UPDATE matters SET reported_date = NULL WHERE CAST(reported_date AS CHAR) = '0000-00-00'");
                DB::statement("UPDATE matters SET submitted_date = NULL WHERE CAST(submitted_date AS CHAR) = '0000-00-00'");
                DB::statement("UPDATE matters SET last_action_date = NULL WHERE CAST(last_action_date AS CHAR) = '0000-00-00'");
            }

            Schema::table('matters', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    // Check if foreign key exists before dropping
                    $foreignKeys = DB::select(
                        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                         WHERE TABLE_NAME = 'matters' AND COLUMN_NAME = 'level_id' AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = DATABASE()"
                    );
                    if (! empty($foreignKeys)) {
                        $table->dropForeign(['level_id']);
                    }
                }
                $table->string('level_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->bigInteger('level_id')->nullable()->change();
            });
        }
    }
};
