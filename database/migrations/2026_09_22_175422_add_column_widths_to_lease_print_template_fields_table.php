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
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            // Per-column width percentages for the `installments_table`
            // field only — [column key => percent of the table's own box
            // width]. Any column left out shares what's left over evenly.
            $table->json('column_widths')->nullable()->after('height_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            $table->dropColumn('column_widths');
        });
    }
};
