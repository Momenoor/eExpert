<?php

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
        if (Schema::hasTable('types')) {
            // First, convert 'true'/'false' enum values to temporary boolean-compatible integers
            DB::statement("UPDATE types SET active = '1' WHERE active = 'true'");
            DB::statement("UPDATE types SET active = '0' WHERE active = 'false'");

            Schema::table('types', function (Blueprint $table) {
                $table->boolean('active')->default(true)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('types')) {
            Schema::table('types', function (Blueprint $table) {
                $table->enum('active', ['true', 'false'])->default('true')->change();
            });

            DB::statement("UPDATE types SET active = 'true' WHERE active = '1'");
            DB::statement("UPDATE types SET active = 'false' WHERE active = '0'");
        }
    }
};
