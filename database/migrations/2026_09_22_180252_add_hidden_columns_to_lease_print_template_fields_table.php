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
            // Which of the `installments_table` field's own columns to
            // leave off the print entirely — a list of column keys.
            $table->json('hidden_columns')->nullable()->after('column_widths');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            $table->dropColumn('hidden_columns');
        });
    }
};
