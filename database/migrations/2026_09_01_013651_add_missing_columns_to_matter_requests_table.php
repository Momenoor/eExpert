<?php

use App\Models\User;
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
        if (Schema::hasTable('matter_requests')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                // Never created on a fresh install: the original migration that adds it
                // is guarded against the pre-rename 'requests' table name, so it silently
                // no-ops once matter_requests is created directly by the base migration.
                if (! Schema::hasColumn('matter_requests', 'extra')) {
                    $table->json('extra')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('matter_requests', 'request_by')) {
                    $table->foreignIdFor(User::class, 'request_by')->nullable()->after('matter_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('matter_requests', 'approved_by')) {
                    $table->foreignIdFor(User::class, 'approved_by')->nullable()->after('status')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('matter_requests', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matter_requests')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                if (Schema::hasColumn('matter_requests', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
                if (Schema::hasColumn('matter_requests', 'approved_by')) {
                    $table->dropConstrainedForeignId('approved_by');
                }
                if (Schema::hasColumn('matter_requests', 'request_by')) {
                    $table->dropConstrainedForeignId('request_by');
                }
                if (Schema::hasColumn('matter_requests', 'extra')) {
                    $table->dropColumn('extra');
                }
            });
        }
    }
};
