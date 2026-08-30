<?php

use App\Models\MatterRequest;
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
                if (! Schema::hasColumn('attachments', 'matter_request_id')) {
                    $table->foreignIdFor(MatterRequest::class)->nullable()->after('matter_id')->constrained();
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
                if (Schema::hasColumn('attachments', 'matter_request_id')) {
                    $table->dropConstrainedForeignIdFor(MatterRequest::class);
                }
            });
        }
    }
};
