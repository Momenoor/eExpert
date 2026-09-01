<?php

use App\Models\Matter;
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
        // Used by the self-referential parent/children relation (supplementary
        // matters) and MatterResource's default sort, but never migrated on a
        // fresh install — only ever added directly against the live DB.
        if (Schema::hasTable('matters') && ! Schema::hasColumn('matters', 'parent_id')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->foreignIdFor(Matter::class, 'parent_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matters') && Schema::hasColumn('matters', 'parent_id')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }
};
