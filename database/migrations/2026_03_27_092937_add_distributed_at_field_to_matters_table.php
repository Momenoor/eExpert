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
                if (Schema::hasColumn('matters', 'received_at') && ! Schema::hasColumn('matters', 'distributed_at')) {
                    // Existing data: the old received_at column IS what distributed_at means.
                    $table->renameColumn('received_at', 'distributed_at');
                } elseif (! Schema::hasColumn('matters', 'distributed_at')) {
                    // Fresh install: neither column exists yet.
                    $table->date('distributed_at')->nullable()->after('next_session_date')->comment('تاريخ توزيع القضية على الخبير');
                }

                if (! Schema::hasColumn('matters', 'received_at')) {
                    $table->date('received_at')->nullable()->after('distributed_at')->comment('تاريخ استلام القضية من المحكمة');
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
                if (Schema::hasColumn('matters', 'received_at')) {
                    $table->dropColumn('received_at');
                }
                if (Schema::hasColumn('matters', 'distributed_at')) {
                    $table->dropColumn('distributed_at');
                }
            });
        }
    }
};
