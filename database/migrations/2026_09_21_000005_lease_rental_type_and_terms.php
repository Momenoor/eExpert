<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('units')->where('unit_type', 'appartment')->update(['unit_type' => 'apartment']);

        // The unit carries the rental type; leases inherit their contract
        // type from it.
        Schema::table('units', function (Blueprint $table) {
            $table->string('rental_type')->nullable()->after('unit_type');
        });

        // "Multiple rent amount" stops being a money figure and becomes a
        // yes/no. Any lease that had an amount recorded becomes "yes".
        Schema::table('leases', function (Blueprint $table) {
            $table->string('multiple_rent_amount_new', 3)->default('no');
        });

        DB::table('leases')->where('multiple_rent_amount', '>', 0)->update(['multiple_rent_amount_new' => 'yes']);

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('multiple_rent_amount');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->renameColumn('multiple_rent_amount_new', 'multiple_rent_amount');
        });

        // Contract type takes new values; anything left from the old
        // commercial/residential/industrial list has no equivalent.
        DB::table('leases')
            ->whereNotNull('contract_type')
            ->whereNotIn('contract_type', ['family', 'bachelors', 'labour', 'employees', 'shop', 'warehouse', 'store', 'office', 'land', 'parking'])
            ->update(['contract_type' => null]);

        // Designated use is removed altogether, along with any print-template
        // field that was placed for it.
        DB::table('lease_print_template_fields')->where('field_key', 'designated_use')->delete();

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('designated_use');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('designated_use')->nullable();
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->decimal('multiple_rent_amount_old', 12, 2)->nullable();
        });

        DB::table('leases')->where('multiple_rent_amount', 'yes')->update(['multiple_rent_amount_old' => 1]);

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('multiple_rent_amount');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->renameColumn('multiple_rent_amount_old', 'multiple_rent_amount');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('rental_type');
        });
    }
};
