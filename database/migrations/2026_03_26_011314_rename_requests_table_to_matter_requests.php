<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('requests') && ! Schema::hasTable('matter_requests')) {
            Schema::rename('requests', 'matter_requests');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matter_requests') && ! Schema::hasTable('requests')) {
            Schema::rename('matter_requests', 'requests');
        }
    }
};
