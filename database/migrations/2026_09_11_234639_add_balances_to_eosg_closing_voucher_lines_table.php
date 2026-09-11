<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rollforward either side of a year's movement — needed to show Opening
 * Balance / Current Year Amount / Closing Balance per employee, not just the
 * movement that gets posted to the journal voucher.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('eosg_closing_voucher_lines')) {
            Schema::table('eosg_closing_voucher_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('eosg_closing_voucher_lines', 'opening_balance')) {
                    $table->decimal('opening_balance', 12, 2)->default(0)->after('party_id');
                }

                if (! Schema::hasColumn('eosg_closing_voucher_lines', 'closing_balance')) {
                    $table->decimal('closing_balance', 12, 2)->default(0)->after('amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('eosg_closing_voucher_lines')) {
            Schema::table('eosg_closing_voucher_lines', function (Blueprint $table) {
                if (Schema::hasColumn('eosg_closing_voucher_lines', 'opening_balance')) {
                    $table->dropColumn('opening_balance');
                }

                if (Schema::hasColumn('eosg_closing_voucher_lines', 'closing_balance')) {
                    $table->dropColumn('closing_balance');
                }
            });
        }
    }
};
