<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract type is chosen on the lease (filtered by the units'
     * classification), not defined on the unit.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'rental_type')) {
                $table->dropColumn('rental_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('rental_type')->nullable()->after('unit_type');
        });
    }
};
