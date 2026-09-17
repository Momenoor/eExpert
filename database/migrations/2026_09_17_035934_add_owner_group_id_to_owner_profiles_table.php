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
        Schema::table('owner_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_profiles', 'owner_group_id')) {
                $table->foreignId('owner_group_id')->nullable()->after('party_id')
                    ->constrained('owner_groups')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('owner_profiles', 'owner_group_id')) {
                $table->dropConstrainedForeignId('owner_group_id');
            }
        });
    }
};
