<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MatterReceivedNotificationController has always written 'email_action' when a
 * request is resolved from an email link, but the column never existed and was
 * not fillable — so Eloquent silently dropped it and the record of HOW a request
 * was resolved was lost every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matter_requests') && ! Schema::hasColumn('matter_requests', 'email_action')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                $table->string('email_action')->nullable()->after('approved_comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('matter_requests') && Schema::hasColumn('matter_requests', 'email_action')) {
            Schema::table('matter_requests', function (Blueprint $table) {
                $table->dropColumn('email_action');
            });
        }
    }
};
