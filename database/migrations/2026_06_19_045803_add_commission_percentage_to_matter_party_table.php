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
        Schema::table('matter_party', function (Blueprint $table) {
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matter_party', function (Blueprint $table) {
            $table->dropColumn('commission_percentage');
        });
    }
};
