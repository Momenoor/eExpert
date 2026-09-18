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
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'government_number') && ! Schema::hasColumn('properties', 'plot_number')) {
                $table->renameColumn('government_number', 'plot_number');
            }

            if (Schema::hasColumn('properties', 'city')) {
                $table->dropColumn('city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'city')) {
                $table->string('city')->nullable()->after('emirate');
            }

            if (Schema::hasColumn('properties', 'plot_number') && ! Schema::hasColumn('properties', 'government_number')) {
                $table->renameColumn('plot_number', 'government_number');
            }
        });
    }
};
