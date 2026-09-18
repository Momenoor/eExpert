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
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'number_of_rooms')) {
                $table->unsignedSmallInteger('number_of_rooms')->nullable()->after('area_sqm');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'nationality')) {
                $table->string('nationality')->nullable()->after('tenant_type');
            }
        });

        Schema::table('owner_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_profiles', 'nationality')) {
                $table->string('nationality')->nullable()->after('identification_number');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            if (! Schema::hasColumn('leases', 'number_of_occupants')) {
                $table->unsignedSmallInteger('number_of_occupants')->nullable()->after('designated_use');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'number_of_rooms')) {
                $table->dropColumn('number_of_rooms');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'nationality')) {
                $table->dropColumn('nationality');
            }
        });

        Schema::table('owner_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('owner_profiles', 'nationality')) {
                $table->dropColumn('nationality');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'number_of_occupants')) {
                $table->dropColumn('number_of_occupants');
            }
        });
    }
};
