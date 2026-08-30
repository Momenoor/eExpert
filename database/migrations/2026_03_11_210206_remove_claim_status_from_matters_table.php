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
        if (Schema::hasTable('matters') && Schema::hasColumn('matters', 'claim_status')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->dropColumn('claim_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matters') && ! Schema::hasColumn('matters', 'claim_status')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->string('claim_status')->nullable();
            });
        }
    }
};
