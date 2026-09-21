<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_group_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_group_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_no')->nullable();
            $table->string('iban')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // The single account each group used to carry becomes its first
        // (default) row, so no existing banking detail is lost.
        DB::table('owner_groups')
            ->where(fn ($query) => $query->whereNotNull('bank_name')->orWhereNotNull('bank_account_no')->orWhereNotNull('iban'))
            ->get(['id', 'bank_name', 'bank_account_no', 'iban'])
            ->each(fn ($group) => DB::table('owner_group_bank_accounts')->insert([
                'owner_group_id' => $group->id,
                'bank_name' => $group->bank_name ?? '—',
                'account_no' => $group->bank_account_no,
                'iban' => $group->iban,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('owner_groups', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_no', 'iban']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('owner_group_id')->nullable()->after('property_number')->constrained()->nullOnDelete();
            $table->foreignId('owner_group_bank_account_id')->nullable()->after('owner_group_id')
                ->constrained('owner_group_bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_group_bank_account_id');
            $table->dropConstrainedForeignId('owner_group_id');
        });

        Schema::table('owner_groups', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('iban')->nullable();
        });

        Schema::dropIfExists('owner_group_bank_accounts');
    }
};
