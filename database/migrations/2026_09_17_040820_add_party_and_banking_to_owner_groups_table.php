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
            // The group's own contact identity (name/phone/email) — a group
            // is a party in its own right (the estate, not any one heir),
            // with its own bank account and TRN below, so it gets a Party
            // record exactly like an individual owner does.
            if (! Schema::hasColumn('owner_groups', 'party_id')) {
                $table->foreignId('party_id')->after('id')->constrained('parties')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('owner_groups', 'trn')) {
                $table->string('trn')->nullable();
            }

            if (! Schema::hasColumn('owner_groups', 'bank_name')) {
                $table->string('bank_name')->nullable();
            }

            if (! Schema::hasColumn('owner_groups', 'bank_account_no')) {
                $table->string('bank_account_no')->nullable();
            }

            if (! Schema::hasColumn('owner_groups', 'iban')) {
                $table->string('iban')->nullable();
            }

            if (Schema::hasColumn('owner_groups', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_groups', 'name')) {
                $table->string('name')->default('');
            }

            foreach (['bank_name', 'bank_account_no', 'iban', 'trn'] as $column) {
                if (Schema::hasColumn('owner_groups', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('owner_groups', 'party_id')) {
                $table->dropConstrainedForeignId('party_id');
            }
        });
    }
};
