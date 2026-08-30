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
        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                if (! Schema::hasColumn('attachments', 'user_id')) {
                    $table->foreignIdFor(User::class)->after('matter_id')->constrained()->onDelete('cascade');
                }
                if (! Schema::hasColumn('attachments', 'path')) {
                    $table->string('path')->after('name')->nullable();
                }
                if (! Schema::hasColumn('attachments', 'type')) {
                    $table->string('type')->after('name')->nullable();
                }
                if (Schema::hasColumn('attachments', 'mime')) {
                    $table->renameColumn('mime', 'size');
                }
                if (Schema::hasColumn('attachments', 'extention')) {
                    $table->renameColumn('extention', 'extension');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                if (Schema::hasColumn('attachments', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('attachments', 'path')) {
                    $table->dropColumn('path');
                }
                if (Schema::hasColumn('attachments', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
                if (Schema::hasColumn('attachments', 'size')) {
                    $table->renameColumn('size', 'mime');
                }
                if (Schema::hasColumn('attachments', 'extension')) {
                    $table->renameColumn('extension', 'extentions');
                }
            });
        }
    }
};
