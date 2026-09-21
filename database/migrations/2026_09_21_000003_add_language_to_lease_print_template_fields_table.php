<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_print_template_fields', 'language')) {
                // 'ar' | 'en' — which language a translatable value (an enum
                // label, Yes/No, a duration) prints in. Null keeps the
                // app's current language, so existing fields are unchanged.
                $table->string('language', 2)->nullable()->after('rtl');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_print_template_fields', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
