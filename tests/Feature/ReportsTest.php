<?php

namespace Tests\Feature;

use App\Enums\FeeType;
use App\Filament\Pages\FeeCollectionAgingReport;
use App\Filament\Pages\MatterQualityReport;
use App\Filament\Pages\OverdueMattersReport;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    private function tableQuery(object $page): Builder
    {
        return (new ReflectionMethod($page, 'getTableQuery'))->invoke($page);
    }

    // ── Fee Collection & Aging ───────────────────────────────────────────────

    public function test_aging_reports_the_balance_still_owed(): void
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 10000,
            'date' => now()->subDays(45),
        ]);
        Allocation::factory()->for($fee)->create(['amount' => 4000]);

        $row = $this->tableQuery(new FeeCollectionAgingReport)->firstWhere('id', $matter->id);

        $this->assertEquals(10000.0, (float) $row->owed_amount);
        $this->assertEquals(4000.0, (float) $row->received_amount);
        $this->assertEquals(6000.0, (float) $row->outstanding_amount);
        $this->assertEquals(45, (int) $row->days_outstanding);
    }

    public function test_aging_settles_a_commission_matter_to_zero(): void
    {
        // A commission matter recorded correctly: each line collected to exactly
        // its own amount. Net billed is 3000 - 750 = 2250 and net received is
        // 3000 - 750 = 2250, so the matter is settled.
        $matter = Matter::factory()->create();

        $expertFee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 3000,
            'date' => now()->subDays(10),
        ]);
        $officeShare = Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
            'date' => now()->subDays(10),
        ]);

        Allocation::factory()->for($expertFee)->create(['amount' => 3000]);
        Allocation::factory()->for($officeShare)->create(['amount' => -750]);

        $row = $this->tableQuery(new FeeCollectionAgingReport)->firstWhere('id', $matter->id);

        $this->assertEquals(2250.0, (float) $row->owed_amount);
        $this->assertEquals(2250.0, (float) $row->received_amount);
        $this->assertEquals(0.0, (float) $row->outstanding_amount);
    }

    public function test_a_paid_vat_line_is_not_reported_as_over_collection(): void
    {
        // Regression: billed counted revenue fees only while received counted
        // allocations from every line, so a fully paid VAT line surfaced as
        // over-collection of exactly the VAT amount — on 55 production matters.
        $matter = Matter::factory()->create();

        $expertFee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 1000,
            'date' => now()->subDay(),
        ]);
        $vat = Fee::factory()->for($matter)->create([
            'type' => FeeType::VAT,
            'amount' => 50,
            'date' => now()->subDay(),
        ]);

        Allocation::factory()->for($expertFee)->create(['amount' => 1000]);
        Allocation::factory()->for($vat)->create(['amount' => 50]);

        $row = $this->tableQuery(new FeeCollectionAgingReport)->firstWhere('id', $matter->id);

        // VAT counts on BOTH sides, so the matter is settled, not over-collected.
        $this->assertEquals(1050.0, (float) $row->owed_amount);
        $this->assertEquals(1050.0, (float) $row->received_amount);
        $this->assertEquals(0.0, (float) $row->outstanding_amount);
    }

    // ── Overdue Matters ──────────────────────────────────────────────────────

    public function test_overdue_lists_only_open_matters_and_ages_them(): void
    {
        $open = Matter::factory()->create([
            'distributed_at' => now()->subDays(100),
            'final_report_at' => null,
        ]);
        $closed = Matter::factory()->finalReported()->create([
            'distributed_at' => now()->subDays(100),
        ]);

        $rows = $this->tableQuery(new OverdueMattersReport)->get();

        $this->assertTrue($rows->contains('id', $open->id));
        $this->assertFalse($rows->contains('id', $closed->id));
        $this->assertEquals(100, (int) $rows->firstWhere('id', $open->id)->days_open);
    }

    public function test_overdue_working_days_exclude_weekends_and_leave(): void
    {
        $assistant = Party::factory()->assistant()->create();
        $matter = Matter::factory()->create([
            'distributed_at' => now()->subDays(30),
            'final_report_at' => null,
        ]);
        MatterParty::create([
            'matter_id' => $matter->id,
            'party_id' => $assistant->id,
            'role' => 'expert',
            'type' => 'assistant',
        ]);

        $page = new OverdueMattersReport;
        $method = new ReflectionMethod($page, 'workingDaysOpen');
        $withoutLeave = $method->invoke($page, $matter);

        // 30 calendar days always contains weekend days, so working days must
        // be strictly fewer.
        $this->assertLessThan(30, $withoutLeave);
        $this->assertGreaterThan(0, $withoutLeave);
    }

    // ── Quality & Rework ─────────────────────────────────────────────────────

    public function test_quality_report_only_includes_matters_with_a_flag(): void
    {
        $reviewed = Matter::factory()->create(['review_count' => 2]);
        $penalised = Matter::factory()->create(['has_court_penalty' => true]);
        $substantive = Matter::factory()->create(['has_substantive_changes' => true]);
        $clean = Matter::factory()->create([
            'review_count' => 0,
            'has_court_penalty' => false,
            'has_substantive_changes' => false,
        ]);

        $rows = $this->tableQuery(new MatterQualityReport)->get();

        $this->assertTrue($rows->contains('id', $reviewed->id));
        $this->assertTrue($rows->contains('id', $penalised->id));
        $this->assertTrue($rows->contains('id', $substantive->id));
        $this->assertFalse($rows->contains('id', $clean->id));
    }

    public function test_quality_lateness_matches_the_incentive_deduction_measure(): void
    {
        // Memo on a Monday, submitted the Friday of the same week: four working
        // days, and the weekend is never counted.
        $matter = Matter::factory()->create([
            'review_count' => 1,
            'final_report_memo_date' => '2026-06-01',   // Monday
            'final_report_at' => '2026-06-05',          // Friday
        ]);

        $page = new MatterQualityReport;
        $late = (new ReflectionMethod($page, 'lateDays'))->invoke($page, $matter);

        $this->assertSame(4, $late);
    }

    public function test_quality_lateness_is_null_without_both_dates(): void
    {
        $matter = Matter::factory()->create([
            'review_count' => 1,
            'final_report_memo_date' => null,
            'final_report_at' => now(),
        ]);

        $page = new MatterQualityReport;

        $this->assertNull((new ReflectionMethod($page, 'lateDays'))->invoke($page, $matter));
    }
}
