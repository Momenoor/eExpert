<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Filament\Widgets\PMSOverviewWidget;
use App\Models\Contract;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContractService;
use App\Services\InstallmentGenerator;
use Database\Seeders\AllPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PMSOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AllPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    private function contract(): Contract
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(ContractService::class)->createFromRawInputs([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);
    }

    public function test_the_widget_counts_vacant_units_overdue_instalments_and_upcoming_renewals(): void
    {
        Unit::factory()->residential()->create(); // vacant

        $contract = $this->contract();
        $installment = app(InstallmentGenerator::class)->generateSchedule($contract, 1)->first();
        $installment->forceFill([
            'payment_status' => InstallmentPaymentStatus::OVERDUE,
            'balance_due' => 5000,
        ])->save();

        // The app's default locale is Arabic, so the description renders
        // translated — assert the figure itself landed correctly rather
        // than the (locale-dependent) English wording around it.
        Livewire::test(PMSOverviewWidget::class)
            ->assertSee('1 / 2')
            ->assertSee(__(':amount AED outstanding', ['amount' => '5,000.00']));
    }
}
