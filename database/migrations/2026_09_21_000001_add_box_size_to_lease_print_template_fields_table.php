<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_print_template_fields', 'width_percent')) {
                $table->decimal('width_percent', 6, 3)->nullable()->after('y_percent');
            }

            if (! Schema::hasColumn('lease_print_template_fields', 'height_percent')) {
                $table->decimal('height_percent', 6, 3)->nullable()->after('width_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            $table->dropColumn(['width_percent', 'height_percent']);
        });
    }
};
