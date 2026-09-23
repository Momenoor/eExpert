<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_no')->nullable()->unique()
                ->comment('Internal payroll number, distinct from the MOHRE personal number');
            $table->string('designation')->nullable()
                ->comment('Official job title as printed on the labour card');
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
            $table->boolean('include_in_salary_authorization_form')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->string('display_name')->nullable();
            $table->boolean('is_eosg_applicable')->default(true)
                ->comment('Not every employee accrues gratuity (e.g. certain contract types)');
            $table->decimal('opening_leave_balance', 6, 1)->default(0)
                ->comment('Days carried over from before this system tracked leave');
            $table->decimal('opening_eosg_balance', 12, 2)->default(0)
                ->comment('Gratuity (AED) entered once by HR/Finance for service before this system tracked it; added to whichever closing voucher is generated first for the employee');
            $table->decimal('eosg_paid_amount', 12, 2)->default(0)
                ->comment('Cumulative gratuity actually paid out to the employee, entered by HR/Finance');
            $table->date('eosg_paid_at')->nullable()
                ->comment('Date of the most recent gratuity payment, if any');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
