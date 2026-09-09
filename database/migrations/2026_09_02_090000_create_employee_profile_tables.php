<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee identity and pay structure.
 *
 * Kept off `parties` deliberately. That table holds 7,000-odd rows that are
 * overwhelmingly clients, opponents and representatives; twenty passport, visa
 * and IBAN columns would be null on nearly all of them, and salary would end up
 * on the row every matter screen already loads. A separate table keeps the
 * personal data behind its own Shield permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();

                $table->string('employee_no')->nullable()->unique()
                    ->comment('Internal payroll number, distinct from the MOHRE personal number');
                $table->string('designation')->nullable()
                    ->comment('Official job title as printed on the labour card');

                // Service dates: gratuity is measured from date_of_joining, never
                // from created_at — a profile entered years late must not shorten
                // the entitlement.
                $table->date('date_of_joining')->nullable();
                $table->date('date_of_leaving')->nullable()->comment('Null while employed');

                $table->string('passport_no')->nullable();
                $table->date('passport_expiry')->nullable()->index();

                $table->string('emirates_id_no', 20)->nullable()
                    ->comment('784-YYYY-NNNNNNN-N, stored with separators');
                $table->date('emirates_id_expiry')->nullable()->index();

                $table->string('labour_card_no')->nullable();
                $table->string('mohre_personal_no', 20)->nullable()
                    ->comment('MOHRE Personal Number — the WPS employee key');
                $table->date('labour_card_expiry')->nullable()->index();

                $table->string('residency_visa_no')->nullable();
                $table->string('visa_file_no')->nullable();
                $table->date('residency_expiry')->nullable()->index();
                $table->string('sponsor_name')->nullable()
                    ->comment('Employing entity where it differs from the office');

                $table->string('bank_name')->nullable();
                $table->string('bank_account_no')->nullable();
                $table->string('iban', 34)->nullable()->comment('AE + 21 digits');
                $table->string('wps_routing_code', 9)->nullable()
                    ->comment('9-digit CBUAE routing code of the salary bank');

                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employee_salary_components')) {
            Schema::create('employee_salary_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();

                $table->string('component', 32)
                    ->comment('basic, housing, transport, utilities, other');
                $table->decimal('amount', 12, 2)->comment('Monthly AED');

                // A raise inserts a new row and closes the old one. Editing in
                // place would retroactively change payslips and gratuity accruals
                // that were already booked.
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->comment('Null = current');

                $table->timestamps();

                $table->index(['party_id', 'component', 'effective_from'], 'esc_party_component_from_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_profiles');
    }
};
