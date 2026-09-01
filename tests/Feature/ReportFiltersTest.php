<?php

namespace Tests\Feature;

use App\Enums\FeeType;
use App\Filament\Pages\Reports\AssistantPerformanceReport;
use App\Filament\Pages\Reports\CourtWorkloadReport;
use App\Filament\Pages\Reports\DeductionsReconciliationReport;
use App\Filament\Pages\Reports\FeeCollectionAgingReport;
use App\Filament\Pages\Reports\MatterQualityReport;
use App\Filament\Pages\Reports\MattersMonthlyReport;
use App\Filament\Pages\Reports\MyMattersReport;
use App\Filament\Pages\Reports\OverdueMattersReport;
use App\Filament\Pages\Reports\TypeProfitabilityReport;
use App\Filament\Pages\Reports\VatSummaryReport;
use App\Models\Allocation;
use App\Models\Court;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every report filter, exercised through the real Livewire component.
 *
 * Testing getTableQuery() in isolation — which is all the reports had — cannot
 * see a filter at all, and that blind spot hid a whole class of failure:
 * Filament runs each filter's ->query() closure inside a nested
 * `where(function ($query) { ... })` group, and Laravel copies only the wheres
 * out of such a group. A havingRaw() written there is dropped from the SQL
 * entirely. No exception, no warning, no effect — the filter simply does
 * nothing, which is exactly how the aging report came to list every matter
 * including the fully settled ones.
 *
 * So each test here asserts on records the component actually renders.
 */
class ReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    private function settledMatter(): Matter
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 5000,
            'date' => now()->subDays(10),
        ]);
        Allocation::factory()->for($fee)->create(['amount' => 5000]);

        return $matter;
    }

    private function owingMatter(int $daysOld = 120): Matter
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 8000,
            'date' => now()->subDays($daysOld),
        ]);
        Allocation::factory()->for($fee)->create(['amount' => 1000]);

        return $matter;
    }

    private function assistantOn(Matter $matter): Party
    {
        $party = Party::factory()->assistant()->create();

        MatterParty::create([
            'matter_id' => $matter->id,
            'party_id' => $party->id,
            'role' => 'expert',
            'type' => 'assistant',
        ]);

        return $party;
    }

    // ── Fee Collection & Aging ───────────────────────────────────────────────

    public function test_aging_hides_settled_matters_by_default(): void
    {
        $settled = $this->settledMatter();
        $owing = $this->owingMatter();

        Livewire::test(FeeCollectionAgingReport::class)
            ->assertCanSeeTableRecords([$owing])
            ->assertCanNotSeeTableRecords([$settled]);
    }

    public function test_aging_shows_settled_matters_once_the_filter_is_cleared(): void
    {
        $settled = $this->settledMatter();
        $owing = $this->owingMatter();

        Livewire::test(FeeCollectionAgingReport::class)
            ->filterTable('outstanding_only', false)
            ->assertCanSeeTableRecords([$owing, $settled]);
    }

    public function test_aging_bucket_filter_narrows_to_that_age_band(): void
    {
        $fresh = $this->owingMatter(10);
        $ancient = $this->owingMatter(200);

        Livewire::test(FeeCollectionAgingReport::class)
            ->filterTable('aging_bucket', '90+')
            ->assertCanSeeTableRecords([$ancient])
            ->assertCanNotSeeTableRecords([$fresh]);
    }

    public function test_aging_first_billed_range_filter_applies(): void
    {
        $recent = $this->owingMatter(10);
        $old = $this->owingMatter(200);

        Livewire::test(FeeCollectionAgingReport::class)
            ->filterTable('billed_between', ['billed_from' => now()->subDays(30)->toDateString()])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_aging_court_filter_applies(): void
    {
        $courtA = Court::factory()->create();
        $courtB = Court::factory()->create();

        $inA = $this->owingMatter();
        $inA->update(['court_id' => $courtA->id]);
        $inB = $this->owingMatter();
        $inB->update(['court_id' => $courtB->id]);

        Livewire::test(FeeCollectionAgingReport::class)
            ->filterTable('court_id', $courtA->id)
            ->assertCanSeeTableRecords([$inA])
            ->assertCanNotSeeTableRecords([$inB]);
    }

    // ── Deductions Reconciliation ────────────────────────────────────────────

    public function test_reconciliation_unbalanced_filter_hides_settled_matters(): void
    {
        $balanced = Matter::factory()->create();
        $expert = Fee::factory()->for($balanced)->create(['type' => FeeType::EXPERT_FEE, 'amount' => 3000]);
        $share = Fee::factory()->for($balanced)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);
        Allocation::factory()->for($expert)->create(['amount' => 3000]);
        Allocation::factory()->for($share)->create(['amount' => -750]);

        $unbalanced = Matter::factory()->create();
        Fee::factory()->for($unbalanced)->create(['type' => FeeType::EXPERT_FEE, 'amount' => 4000]);
        Fee::factory()->for($unbalanced)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 1000]);

        Livewire::test(DeductionsReconciliationReport::class)
            ->filterTable('unbalanced_only')
            ->assertCanSeeTableRecords([$unbalanced])
            ->assertCanNotSeeTableRecords([$balanced]);
    }

    public function test_reconciliation_wrong_signed_filter_finds_positive_deduction_fees(): void
    {
        $clean = Matter::factory()->create();
        Fee::factory()->for($clean)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);

        $legacy = Matter::factory()->create();
        $fee = Fee::factory()->for($legacy)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);
        // Bypass the model hook: this is the legacy shape it now prevents.
        DB::table('fees')->where('id', $fee->id)->update(['amount' => 750]);

        Livewire::test(DeductionsReconciliationReport::class)
            ->filterTable('wrong_signed_only')
            ->assertCanSeeTableRecords([$legacy])
            ->assertCanNotSeeTableRecords([$clean]);
    }

    // ── Assistant Performance ────────────────────────────────────────────────

    public function test_assistant_performance_open_matters_filter_applies(): void
    {
        $openMatter = Matter::factory()->create(['final_report_at' => null]);
        $busy = $this->assistantOn($openMatter);

        $doneMatter = Matter::factory()->finalReported()->create();
        $free = $this->assistantOn($doneMatter);

        Livewire::test(AssistantPerformanceReport::class)
            ->filterTable('has_open')
            ->assertCanSeeTableRecords([$busy])
            ->assertCanNotSeeTableRecords([$free]);
    }

    // ── Overdue Matters ──────────────────────────────────────────────────────

    public function test_overdue_age_filter_applies(): void
    {
        $old = Matter::factory()->create(['distributed_at' => now()->subDays(120), 'final_report_at' => null]);
        $recent = Matter::factory()->create(['distributed_at' => now()->subDays(10), 'final_report_at' => null]);

        Livewire::test(OverdueMattersReport::class)
            ->filterTable('age', '90')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$recent]);
    }

    public function test_overdue_no_initial_report_filter_applies(): void
    {
        $missing = Matter::factory()->create([
            'distributed_at' => now()->subDays(40),
            'initial_report_at' => null,
            'final_report_at' => null,
        ]);
        $submitted = Matter::factory()->create([
            'distributed_at' => now()->subDays(40),
            'initial_report_at' => now()->subDays(20),
            'final_report_at' => null,
        ]);

        Livewire::test(OverdueMattersReport::class)
            ->filterTable('no_initial_report')
            ->assertCanSeeTableRecords([$missing])
            ->assertCanNotSeeTableRecords([$submitted]);
    }

    // ── Quality & Rework ─────────────────────────────────────────────────────

    public function test_quality_repeat_reviews_filter_applies(): void
    {
        $reviewedTwice = Matter::factory()->create(['review_count' => 3]);
        $penalisedOnly = Matter::factory()->create(['review_count' => 0, 'has_court_penalty' => true]);

        Livewire::test(MatterQualityReport::class)
            ->filterTable('repeat_reviews')
            ->assertCanSeeTableRecords([$reviewedTwice])
            ->assertCanNotSeeTableRecords([$penalisedOnly]);
    }

    // ── VAT Summary ──────────────────────────────────────────────────────────

    public function test_vat_uncollected_filter_applies(): void
    {
        $matter = Matter::factory()->create();

        $paid = Fee::factory()->for($matter)->create(['type' => FeeType::VAT, 'amount' => 100, 'date' => now()]);
        Allocation::factory()->for($paid)->create(['amount' => 100]);

        $unpaid = Fee::factory()->for($matter)->create(['type' => FeeType::VAT, 'amount' => 200, 'date' => now()]);

        Livewire::test(VatSummaryReport::class)
            ->filterTable('uncollected_only')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid]);
    }

    public function test_vat_period_filter_applies(): void
    {
        $matter = Matter::factory()->create();

        $inPeriod = Fee::factory()->for($matter)->create([
            'type' => FeeType::VAT, 'amount' => 100, 'date' => now()->subDays(5),
        ]);
        $outOfPeriod = Fee::factory()->for($matter)->create([
            'type' => FeeType::VAT, 'amount' => 100, 'date' => now()->subDays(300),
        ]);

        Livewire::test(VatSummaryReport::class)
            ->filterTable('period', ['from' => now()->subDays(30)->toDateString()])
            ->assertCanSeeTableRecords([$inPeriod])
            ->assertCanNotSeeTableRecords([$outOfPeriod]);
    }

    // ── Court Workload / Type Profitability ──────────────────────────────────

    public function test_court_workload_open_matters_filter_applies(): void
    {
        $busyCourt = Court::factory()->create();
        Matter::factory()->create(['court_id' => $busyCourt->id, 'final_report_at' => null]);

        $quietCourt = Court::factory()->create();
        Matter::factory()->finalReported()->create(['court_id' => $quietCourt->id]);

        Livewire::test(CourtWorkloadReport::class)
            ->filterTable('has_open')
            ->assertCanSeeTableRecords([$busyCourt])
            ->assertCanNotSeeTableRecords([$quietCourt]);
    }

    public function test_type_profitability_active_filter_applies(): void
    {
        $active = Type::factory()->create(['active' => true]);
        Matter::factory()->create(['type_id' => $active->id]);

        $retired = Type::factory()->create(['active' => false]);
        Matter::factory()->create(['type_id' => $retired->id]);

        Livewire::test(TypeProfitabilityReport::class)
            ->filterTable('active_only')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$retired]);
    }

    // ── My Matters ───────────────────────────────────────────────────────────

    public function test_my_matters_open_only_filter_is_on_by_default(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->assistant()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $open = Matter::factory()->create(['final_report_at' => null]);
        MatterParty::create(['matter_id' => $open->id, 'party_id' => $party->id, 'role' => 'expert', 'type' => 'assistant']);

        $closed = Matter::factory()->finalReported()->create();
        MatterParty::create(['matter_id' => $closed->id, 'party_id' => $party->id, 'role' => 'expert', 'type' => 'assistant']);

        Livewire::test(MyMattersReport::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    // ── Matters Monthly ──────────────────────────────────────────────────────

    public function test_monthly_report_year_filter_narrows_the_months(): void
    {
        Matter::factory()->create([
            'year' => (string) now()->year,
            'distributed_at' => now()->subMonth(),
        ]);
        Matter::factory()->create([
            'year' => '2019',
            'distributed_at' => '2019-04-15',
        ]);

        $thisYear = Livewire::test(MattersMonthlyReport::class)
            ->instance()->getFilteredTableQuery()->pluck('period')->all();

        $this->assertNotEmpty($thisYear);
        foreach ($thisYear as $period) {
            $this->assertStringStartsWith((string) now()->year, (string) $period);
        }

        $old = Livewire::test(MattersMonthlyReport::class)
            ->filterTable('year', '2019')
            ->instance()->getFilteredTableQuery()->pluck('period')->all();

        $this->assertSame(['2019-04'], array_map('strval', $old));
    }

    public function test_monthly_report_assistant_filter_applies(): void
    {
        // The assistant predicate has to run on a plain query builder too, since
        // the month list is not an Eloquent query. whereHas() threw there.
        $mine = Matter::factory()->create(['year' => '2021', 'distributed_at' => '2021-03-02']);
        $assistant = $this->assistantOn($mine);

        Matter::factory()->create(['year' => '2021', 'distributed_at' => '2021-09-02']);

        $periods = Livewire::test(MattersMonthlyReport::class)
            ->filterTable('year', '2021')
            ->filterTable('assistant', $assistant->id)
            ->instance()->getFilteredTableQuery()->pluck('period')->all();

        $this->assertSame(['2021-03'], array_map('strval', $periods));
    }

    public function test_monthly_report_returns_one_row_per_month(): void
    {
        // Three matters in the same month, each also carrying report dates in
        // that month, so all three union branches contribute the same period.
        foreach ([1, 2, 3] as $day) {
            Matter::factory()->create([
                'year' => '2023',
                'distributed_at' => "2023-05-0{$day}",
                'initial_report_at' => "2023-05-1{$day}",
                'final_report_at' => "2023-05-2{$day}",
            ]);
        }

        $periods = Livewire::test(MattersMonthlyReport::class)
            ->filterTable('year', '2023')
            ->instance()->getFilteredTableQuery()->pluck('period')->all();

        $this->assertSame(['2023-05'], array_map('strval', $periods));
    }

    public function test_monthly_report_gives_every_month_a_distinct_row_key(): void
    {
        // Livewire keys table rows by the record's primary key. Eloquent casts
        // `id` to int, so a period string of '2023-05' arrived as 2023 and every
        // month in a year shared one key — Livewire reused a single row's DOM
        // for all of them and the report showed the same month repeated, with a
        // duplicate-key error on the client.
        foreach (['2023-01-10', '2023-05-10', '2023-09-10'] as $date) {
            Matter::factory()->create(['year' => '2023', 'distributed_at' => $date]);
        }

        $rows = Livewire::test(MattersMonthlyReport::class)
            ->filterTable('year', '2023')
            ->instance()->getFilteredSortedTableQuery()->get();

        $this->assertCount(3, $rows);
        $this->assertSame([202309, 202305, 202301], $rows->pluck('id')->map('intval')->all());
        $this->assertSame(3, $rows->pluck('id')->unique()->count());
    }

    /**
     * Every filter on the monthly report, applied one at a time.
     *
     * The report builds its own query from $this->tableFilters rather than
     * through Filament's filter pipeline, so a filter can only be proven to
     * work — or even to compile — by driving the component.
     */
    public function test_every_monthly_report_filter_applies_without_error(): void
    {
        $court = Court::factory()->create();
        $type = Type::factory()->create();

        $matter = Matter::factory()->create([
            'year' => '2023',
            'distributed_at' => '2023-05-02',
            'court_id' => $court->id,
            'type_id' => $type->id,
        ]);
        $assistant = $this->assistantOn($matter);

        Matter::factory()->create([
            'year' => '2023',
            'distributed_at' => '2023-09-02',
        ]);

        $cases = [
            'year' => '2023',
            'assistant' => $assistant->id,
            'court' => $court->id,
            'type' => $type->id,
            // A matter with no fees settles to NO_FEES; the model recomputes it.
            'collection_status' => ['no_fees'],
            'distributed_at' => ['received_from' => '2023-01-01', 'received_until' => '2023-06-30'],
        ];

        foreach ($cases as $filter => $value) {
            $rows = Livewire::test(MattersMonthlyReport::class)
                ->filterTable('year', '2023')
                ->filterTable($filter, $value)
                ->instance()->getFilteredTableQuery()->get();

            $this->assertNotEmpty($rows, "the {$filter} filter returned nothing");
            $this->assertSame(
                $rows->pluck('period')->unique()->count(),
                $rows->count(),
                "the {$filter} filter produced duplicate month rows",
            );
        }
    }

    public function test_my_matters_shows_no_age_for_a_closed_matter(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->assistant()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $open = Matter::factory()->create([
            'distributed_at' => now()->subDays(40),
            'final_report_at' => null,
        ]);
        $closed = Matter::factory()->create([
            'distributed_at' => now()->subDays(400),
            'initial_report_at' => now()->subDays(380),
            'final_report_at' => now()->subDays(370),
        ]);

        foreach ([$open, $closed] as $matter) {
            MatterParty::create([
                'matter_id' => $matter->id,
                'party_id' => $party->id,
                'role' => 'expert',
                'type' => 'assistant',
            ]);
        }

        $component = Livewire::test(MyMattersReport::class)
            ->filterTable('open_only', false);

        $column = $component->instance()->getTable()->getColumn('days_open');

        $this->assertSame(40, $column->record($open->fresh())->getState());
        $this->assertNull(
            $column->record($closed->fresh())->getState(),
            'a matter with a final report is not open, so it has no age',
        );
    }

    // ── Indicators ───────────────────────────────────────────────────────────

    public function test_an_inactive_filter_shows_no_indicator(): void
    {
        // Overriding indicateUsing with an unconditional closure defeated
        // Filament's own active check, so every checkbox filter displayed a
        // chip whether or not it was switched on — and clicking the chip's
        // remove button reset a filter that had never been applied.
        $this->owingMatter();

        $table = Livewire::test(OverdueMattersReport::class)->instance()->getTable();

        $this->assertEmpty(
            $table->getFilter('no_initial_report')->getIndicators(),
            'a filter that is switched off must not display an indicator',
        );
    }

    public function test_an_active_filter_still_shows_its_indicator(): void
    {
        $this->owingMatter();

        $table = Livewire::test(OverdueMattersReport::class)
            ->filterTable('no_initial_report')
            ->instance()
            ->getTable();

        $this->assertNotEmpty($table->getFilter('no_initial_report')->getIndicators());
    }
}
