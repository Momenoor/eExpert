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
        if (Schema::hasTable('parties')) {
            Schema::table('parties', function (Blueprint $table) {
                if (! Schema::hasColumn('parties', 'role')) {
                    $table->json('role')->nullable()->after('type');
                }
                if (! Schema::hasColumn('parties', 'old_id')) {
                    $table->integer('old_id')->nullable()->after('role');
                }
            });
        }
        if (Schema::hasTable('matter_party')) {
            Schema::table('matter_party', function (Blueprint $table) {
                if (! Schema::hasColumn('matter_party', 'role')) {
                    $table->string('role')->nullable()->after('parent_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('parties')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->dropColumn('role');
                $table->dropColumn('old_id');
            });
        }
        if (Schema::hasTable('matter_party')) {
            Schema::table('matter_party', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
