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
        // ── 1. matter_type_incentive_configs ─────────────────────────────────────────
        // Each matter type has a calculation_type:
        //   tiered  = % based on working days + difficulty (simple/normal/exceptional)
        //   fixed   = fixed % regardless of days (e.g. 8% for liquidation/insolvency)
        //   committee = individual tiered ± 2% adjustment
        Schema::create('matter_type_incentive_configs', function (Blueprint $table) {
            $table->id();
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('type_id')->nullable();
            } else {
                $table->foreignId('type_id')->constrained()->cascadeOnDelete();
                $table->unique('type_id');
            }
            $table->enum('calculation_type', ['tiered', 'fixed', 'committee']);
            $table->decimal('fixed_percentage', 5, 2)->nullable()
                ->comment('Used when calculation_type = fixed (e.g. 8 for 8%)');
            $table->enum('committee_source', ['office', 'external'])->nullable()
                ->comment('office = +2%, external = -2% from tiered base');
            $table->decimal('assistant_rate', 5, 2)->default(100)
                ->comment('% of base incentive allocated to assistants collectively');
            $table->timestamps();
        });

        // ── 2. matter_type_incentive_tiers ────────────────────────────────────────────
        // Tiers: working days from received_date to initial_report_at
        // Per difficulty level (simple / normal / exceptional)
        // e.g. simple + 1-12 days = 9%, simple + 13-15 days = 7%
        Schema::create('matter_type_incentive_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')
                ->constrained('matter_type_incentive_configs')
                ->cascadeOnDelete();
            $table->enum('difficulty', ['simple', 'normal', 'exceptional']);
            $table->unsignedSmallInteger('days_from');
            $table->unsignedSmallInteger('days_to')->nullable()->comment('null = no upper limit');
            $table->decimal('percentage', 5, 2)->comment('Base incentive % of fee amount excl. VAT');
            $table->timestamps();
        });

        // ── 3. incentive_extra_rules ──────────────────────────────────────────────────
        // Per-assistant completed matter count → extra bonus %
        // Per PDF: 5 matters = +1.5%, 6 matters = +2%, >6 matters = +3%
        // Only applies to tiered matters (not fixed/committee)
        // Requires minimum 6 matters — if below 6, deduct 2% per missing matter
        Schema::create('incentive_extra_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('min_count');
            $table->unsignedSmallInteger('max_count')->nullable()->comment('null = no upper limit');
            $table->decimal('extra_percentage', 5, 2);
            $table->timestamps();
        });

        // ── 4. incentive_calculations ─────────────────────────────────────────────────
        // Bi-monthly periods (every 2 months as per PDF)
        Schema::create('incentive_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        // ── 5. incentive_lines ────────────────────────────────────────────────────────
        // One row per matter per fee per calculation
        Schema::create('incentive_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_id')->constrained()->cascadeOnDelete();

            // Days from received_date to initial_report_at (working days)
            $table->unsignedSmallInteger('completion_days')->nullable();
            $table->string('difficulty')->nullable();

            $table->decimal('fee_amount_excl_vat', 12, 2)->default(0);
            $table->decimal('base_percentage', 5, 2)->default(0)->comment('Matched tier % or fixed %');
            $table->decimal('committee_adjustment', 5, 2)->default(0)
                ->comment('+2 for office committee, -2 for external committee');
            $table->decimal('effective_percentage', 5, 2)->default(0)->comment('base + committee adjustment');
            $table->decimal('base_amount', 12, 2)->default(0)->comment('fee_amount_excl_vat × effective_percentage / 100');

            // Deductions applied to this line
            $table->decimal('review_deduction_pct', 5, 2)->default(0)
                ->comment('-2% first major review, -1% subsequent');
            $table->decimal('final_report_deduction_pct', 5, 2)->default(0)
                ->comment('Late final report deduction');
            $table->decimal('total_deduction_pct', 5, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0)->comment('base_amount after deductions');

            $table->timestamps();
            $table->unique(['incentive_calculation_id', 'fee_id']);
        });

        // ── 6. incentive_line_deductions ─────────────────────────────────────────────
        // Audit trail of deductions applied per line
        Schema::create('incentive_line_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_line_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'review_first',         // -2% first review with substantive changes
                'review_subsequent',    // -1% second/later reviews
                'late_final_report',    // late final report deduction
                'court_penalty',        // full exclusion if court penalty
            ]);
            $table->decimal('percentage', 5, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // ── 7. incentive_assistant_lines ─────────────────────────────────────────────
        Schema::create('incentive_assistant_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_amount', 12, 2)->comment('net_amount split equally among assistants');
            $table->decimal('extra_percentage', 5, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('minimum_penalty_pct', 5, 2)->default(0)
                ->comment('-2% per matter below minimum 6 in period');
            $table->decimal('minimum_penalty_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->timestamps();
            $table->unique(['incentive_line_id', 'party_id']);
        });

        // ── 8. incentive_assistant_extras ────────────────────────────────────────────
        Schema::create('incentive_assistant_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('completed_matter_count');
            $table->boolean('meets_minimum')->default(true)
                ->comment('false if completed < 6 matters');
            $table->decimal('minimum_penalty_pct', 5, 2)->default(0)
                ->comment('2% × (6 - completed_count) if below minimum');
            $table->decimal('extra_percentage', 5, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['incentive_calculation_id', 'party_id'], 'inc_asst_extras_calc_party_unique');
        });

        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                if (! Schema::hasColumn('matters', 'review_count')) {
                    $table->unsignedTinyInteger('review_count')->default(0);
                }
                if (! Schema::hasColumn('matters', 'has_substantive_changes')) {
                    $table->boolean('has_substantive_changes')->default(false);
                }
                if (! Schema::hasColumn('matters', 'has_court_penalty')) {
                    $table->boolean('has_court_penalty')->default(false);
                }
                if (! Schema::hasColumn('matters', 'final_report_memo_date')) {
                    $table->date('final_report_memo_date')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentive_tables');
    }
};
