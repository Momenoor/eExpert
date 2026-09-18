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
            if (! Schema::hasColumn('properties', 'emirate')) {
                $table->string('emirate')->nullable()->after('name');
            }
            if (! Schema::hasColumn('properties', 'municipality')) {
                $table->string('municipality')->nullable()->after('city');
            }
            if (! Schema::hasColumn('properties', 'suburb')) {
                $table->string('suburb')->nullable()->after('municipality');
            }
            if (! Schema::hasColumn('properties', 'area')) {
                $table->string('area')->nullable()->after('suburb');
            }
            if (! Schema::hasColumn('properties', 'title_deed_number')) {
                $table->string('title_deed_number')->nullable()->after('area');
            }
            if (! Schema::hasColumn('properties', 'title_deed_date')) {
                $table->date('title_deed_date')->nullable()->after('title_deed_number');
            }
            if (! Schema::hasColumn('properties', 'government_number')) {
                $table->string('government_number')->nullable()->after('title_deed_date');
            }
            if (! Schema::hasColumn('properties', 'property_type')) {
                $table->string('property_type')->nullable()->after('government_number');
            }
            if (! Schema::hasColumn('properties', 'property_number')) {
                $table->string('property_number')->nullable()->after('property_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            foreach ([
                'emirate',
                'municipality',
                'suburb',
                'area',
                'title_deed_number',
                'title_deed_date',
                'government_number',
                'property_type',
                'property_number',
            ] as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
