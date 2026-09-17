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
        Schema::table('owner_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_groups', 'name')) {
                // The group's own name ("Legal Heirs of Mahmoud Kalbat") —
                // authoritative on its own, not derived from the linked
                // Party. The Party exists only to supply phone/email.
                $table->string('name')->after('party_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_groups', function (Blueprint $table) {
            if (Schema::hasColumn('owner_groups', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
