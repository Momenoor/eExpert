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
        if (Schema::hasTable('requests')) {
            Schema::table('requests', function (Blueprint $table) {
                if (! Schema::hasColumn('requests', 'extra')) {
                    $table->json('extra')->nullable()->after('notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('requests')) {
            Schema::table('requests', function (Blueprint $table) {
                if (Schema::hasColumn('requests', 'extra')) {
                    $table->dropColumn('extra');
                }
            });
        }
    }
};
