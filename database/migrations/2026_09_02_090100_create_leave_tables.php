<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave requests, their typed periods, and the entitlement balances behind them.
 *
 * `party_leaves` already exists and is read by
 * IncentiveCalculatorService::monthlyAvailabilityRatio() to prorate an
 * assistant's monthly quota. These tables sit IN FRONT of it as workflow —
 * approval writes through — rather than replacing it. Had the module kept its
 * own leave ledger instead, that proration would have gone on seeing nothing and
 * awarding full quotas to people who were away all month, silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()
                    ->comment('Usually the employee; HR may file on their behalf');

                // Same vocabulary as matter_requests, so RequestStatus and the
                // existing approval UI apply unchanged.
                $table->string('status', 16)->default('pending')->index();

                $table->date('start_date');
                $table->date('end_date');
                $table->text('comment')->nullable()->comment("The employee's reason");

                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('approved_comment')->nullable()
                    ->comment('Rejection reason lands here — same column name as matter_requests');

                $table->timestamps();
                $table->softDeletes();

                $table->index(['party_id', 'start_date']);
            });
        }

        if (! Schema::hasTable('leave_request_periods')) {
            Schema::create('leave_request_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();

                $table->string('leave_type', 24)
                    ->comment('annual, casual, sick_full, sick_half, sick_unpaid, unpaid, absent');
                $table->date('start_date');
                $table->date('end_date');

                $table->decimal('day_count', 4, 1)->comment('Halves allow half-day leave');

                // What payroll actually multiplies by. 1.00 paid, 0.50 half-pay
                // sick, 0.00 unpaid or unauthorised — so the deduction is
                // Σ day_count × (1 − pay_factor) and payroll never needs to know
                // what any given type means.
                $table->decimal('pay_factor', 3, 2)->default(1);

                $table->timestamps();

                $table->index(['leave_request_id', 'leave_type'], 'lrp_request_type_index');
            });
        }

        if (! Schema::hasTable('leave_entitlements')) {
            Schema::create('leave_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();

                // Anniversary of joining, not the calendar year: Article 31's
                // sick-leave ladder resets per YEAR OF SERVICE.
                $table->date('service_year_start');

                $table->decimal('annual_entitled_days', 5, 1)->default(0)
                    ->comment('30 calendar days at 1 year+; 2 days/month between 6 and 12 months');
                $table->decimal('annual_taken_days', 5, 1)->default(0);

                // Which band the next sick day falls into depends on how many
                // have already been taken this service year: 15 full, then 30
                // half, then 45 unpaid.
                $table->decimal('sick_full_taken', 5, 1)->default(0);
                $table->decimal('sick_half_taken', 5, 1)->default(0);
                $table->decimal('sick_unpaid_taken', 5, 1)->default(0);

                $table->timestamps();

                $table->unique(['party_id', 'service_year_start'], 'le_party_year_unique');
            });
        }

        // Extend the existing ledger rather than shadowing it.
        if (Schema::hasTable('party_leaves')) {
            Schema::table('party_leaves', function (Blueprint $table) {
                if (! Schema::hasColumn('party_leaves', 'leave_request_period_id')) {
                    // Nullable on purpose: rows entered by hand before this module
                    // existed stay valid and keep counting toward proration.
                    $table->foreignId('leave_request_period_id')->nullable()->after('party_id')
                        ->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('party_leaves', 'leave_type')) {
                    $table->string('leave_type', 24)->nullable()->after('end_date');
                }
                if (! Schema::hasColumn('party_leaves', 'pay_factor')) {
                    $table->decimal('pay_factor', 3, 2)->nullable()->after('leave_type')
                        ->comment('Null on legacy rows — treated as fully paid');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('party_leaves')) {
            Schema::table('party_leaves', function (Blueprint $table) {
                if (Schema::hasColumn('party_leaves', 'leave_request_period_id')) {
                    $table->dropConstrainedForeignId('leave_request_period_id');
                }
                foreach (['leave_type', 'pay_factor'] as $column) {
                    if (Schema::hasColumn('party_leaves', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_request_periods');
        Schema::dropIfExists('leave_requests');
    }
};
