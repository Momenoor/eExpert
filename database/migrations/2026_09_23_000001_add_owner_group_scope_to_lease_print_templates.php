<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same page/field template system the lease contract print uses,
     * reused for two more document types — but scoped to an owner group
     * instead of a contract format, since a Tax Invoice or Receivable
     * Receipt is that estate's own letterhead, not a government form
     * shared by every landlord.
     *
     * `contract_format` stays NOT NULL/unique exactly as it was — a
     * group-scoped template gets a synthetic value there instead
     * (`LeasePrintTemplate::syntheticFormatFor()`), so no existing
     * constraint needs touching (and no doctrine/dbal, which isn't
     * installed, for a nullability change).
     */
    public function up(): void
    {
        Schema::table('lease_print_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_print_templates', 'document_type')) {
                $table->string('document_type')->default('lease_contract')->after('name');
            }

            if (! Schema::hasColumn('lease_print_templates', 'owner_group_id')) {
                $table->foreignId('owner_group_id')->nullable()->after('document_type')
                    ->constrained('owner_groups')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_print_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_group_id');
            $table->dropColumn('document_type');
        });
    }
};
