<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Filament\Pms\Widgets\PMSOverviewWidget;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\InstallmentGenerator;
use App\Services\PMS\LeaseService;
use Database\Seeders\AllPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PMSOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AllPermissionsSeeder::class);

        // AllPermissionsSeeder no longer creates any roles — the configured
        // super-admin role (config('filament-shield.super_admin.name'),
        // currently `super-admin`, NOT the legacy `super_admin` spelling)
        // bypasses every check via Shield's own Gate::before regardless of
        // assigned permissions, so an empty role is all this test needs,
        // matching InstallWizard::createAdmin()'s own role creation.
        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');
        Role::firstOrCreate(['name' => $superAdminRole, 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole($superAdminRole);
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('pms'));
    }

    private function lease(): Lease
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(LeaseService::class)->createFromRawInputs([
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

        $lease = $this->lease();
        $installment = app(InstallmentGenerator::class)->generateSchedule($lease, 1)->first();
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
