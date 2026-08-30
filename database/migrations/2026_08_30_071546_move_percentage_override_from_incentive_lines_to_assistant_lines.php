<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A manual percentage override now applies to one specific assistant on
     * a matter, not to the whole matter (which used to affect every
     * co-assistant sharing it) — so it moves from incentive_lines down to
     * incentive_assistant_lines.
     */
    public function up(): void
    {
        Schema::table('incentive_assistant_lines', function (Blueprint $table) {
            $table->decimal('percentage_override', 5, 2)->nullable()->after('party_id')
                ->comment('Manually entered percentage that replaces this specific assistant\'s computed share for this matter.');
        });

        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->dropColumn('percentage_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_lines', function (Blueprint $table) {
            $table->decimal('percentage_override', 5, 2)->nullable()->after('base_percentage');
        });

        Schema::table('incentive_assistant_lines', function (Blueprint $table) {
            $table->dropColumn('percentage_override');
        });
    }
};
