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
        if (Schema::hasTable('matter_party')) {
            if (! Schema::hasColumn('matter_party', 'id') && DB::getDriverName() !== 'sqlite') {
                Schema::table('matter_party', function (Blueprint $table) {
                    $table->id()->first();
                });
            }
            if (Schema::hasColumn('matter_party', 'parent_id')) {
                Schema::table('matter_party', function (Blueprint $table) {
                    $table->unsignedBigInteger('parent_id')->nullable()->change();
                });
            } else {
                Schema::table('matter_party', function (Blueprint $table) {
                    $table->unsignedBigInteger('parent_id')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matter_party')) {
            Schema::table('matter_party', function (Blueprint $table) {
                if (Schema::hasColumn('matter_party', 'id')) {
                    $table->dropColumn('id');
                }
            });
        }
    }
};
