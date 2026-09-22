<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_profiles', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('owner_group_id');
            }
        });

        // The first member of each existing group becomes its primary
        // owner, so print output has a deterministic choice from day one
        // instead of every group showing no primary at all.
        DB::table('owner_profiles')
            ->whereNotNull('owner_group_id')
            ->select('owner_group_id')
            ->distinct()
            ->get()
            ->each(function ($row): void {
                $firstId = DB::table('owner_profiles')
                    ->where('owner_group_id', $row->owner_group_id)
                    ->orderBy('id')
                    ->value('id');

                if ($firstId !== null) {
                    DB::table('owner_profiles')->where('id', $firstId)->update(['is_primary' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('owner_profiles', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
