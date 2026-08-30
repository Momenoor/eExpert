<?php

use App\Enums\MatterDifficulty;
use App\Enums\MatterLevel;
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
        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                if (Schema::hasColumn('matters', 'expert_id')) {
                    $table->dropConstrainedForeignId('expert_id');
                }
                if (Schema::hasColumn('matters', 'external_marketing_rate')) {
                    $table->dropColumn('external_marketing_rate');
                }
                if (Schema::hasColumn('matters', 'assign')) {
                    $table->dropColumn('assign');
                }
                if (Schema::hasColumn('matters', 'last_action_date')) {
                    $table->dropColumn('last_action_date');
                }
                if (Schema::hasColumn('matters', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('matters', 'reported_date')) {
                    $table->renameColumn('reported_date', 'initial_report_at');
                }
                if (Schema::hasColumn('matters', 'submitted_date')) {
                    $table->renameColumn('submitted_date', 'final_report_at');
                }
                if (Schema::hasColumn('matters', 'received_date')) {
                    $table->renameColumn('received_date', 'distributed_at');
                }
            });
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'Account_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('Account_id');
            });
        }
        Schema::dropIfExists('cashes');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('matter_expert');
        Schema::dropIfExists('experts');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('marketers');
        Schema::dropIfExists('matter_marketing');
        Schema::dropIfExists('matter_statuses');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('accounts');
        if (Schema::hasTable('matters')) {
            Matter::whereNotNull('final_report_at')
                ->whereNull('initial_report_at')
                ->update(['initial_report_at' => DB::raw('final_report_at')]);
            Matter::whereNull('level')
                ->update(['level' => MatterLevel::FIRST_INSTANCE]);
            Matter::whereNull('difficulty')
                ->update(['difficulty' => MatterDifficulty::EASY]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
