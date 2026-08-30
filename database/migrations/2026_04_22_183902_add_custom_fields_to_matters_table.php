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
            Schema::table('matters', function (Blueprint $table) {
                if (! Schema::hasColumn('matters', 'custom_fields')) {
                    $table->json('custom_fields')->nullable()->after('type_id');
                }
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
                if (Schema::hasColumn('matters', 'custom_fields')) {
                    $table->dropColumn('custom_fields');
                }
            });
        }
    }
};
