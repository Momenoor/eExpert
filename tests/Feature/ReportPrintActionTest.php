<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\AssistantMatterFeesReport;
use App\Filament\Pages\Reports\AssistantMattersReport;
use App\Filament\Pages\Reports\CourtWorkloadReport;
use App\Filament\Pages\Reports\FeeCollectionAgingReport;
use App\Filament\Pages\Reports\MattersMonthlyReport;
use App\Models\User;
use App\Support\ReportPrintAction;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPrintActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    public function test_report_print_action_configures_alpine_click_handler(): void
    {
        $action = ReportPrintAction::make();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('print', $action->getName());
        $this->assertSame('window.print()', $action->getAlpineClickHandler());
        $this->assertFalse($action->isLivewireClickHandlerEnabled());
    }

    public function test_report_pages_render_print_header(): void
    {
        Livewire::test(CourtWorkloadReport::class)
            ->assertSeeHtml('report-print-header')
            ->assertSeeHtml(__('Court Workload'))
            ->assertSeeHtml(__('Applied Filters'));

        Livewire::test(MattersMonthlyReport::class)
            ->assertSeeHtml('report-print-header')
            ->assertSeeHtml(__('Matters Monthly Report'));

        Livewire::test(AssistantMattersReport::class)
            ->assertSeeHtml('report-print-header')
            ->assertSeeHtml(__('Assistant Matters Report'));
    }

    public function test_report_tables_are_not_paginated(): void
    {
        $courtReport = Livewire::test(CourtWorkloadReport::class)->instance();
        $this->assertFalse($courtReport->getTable()->isPaginated());

        $feesReport = Livewire::test(AssistantMatterFeesReport::class)->instance();
        $this->assertFalse($feesReport->getTable()->isPaginated());

        $agingReport = Livewire::test(FeeCollectionAgingReport::class)->instance();
        $this->assertFalse($agingReport->getTable()->isPaginated());
    }
}
