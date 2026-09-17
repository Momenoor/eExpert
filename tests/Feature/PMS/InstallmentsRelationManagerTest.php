<?php

namespace Tests\Feature\PMS;

use App\Filament\Pms\Resources\Leases\Pages\ViewLease;
use App\Filament\Pms\Resources\Leases\RelationManagers\InstallmentsRelationManager;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\InstallmentGenerator;
use App\Services\PMS\LeaseService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstallmentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('pms'));
    }

    private function lease(): Lease
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(LeaseService::class)->createFromRawInputs([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);
    }

    public function test_generating_a_schedule_from_the_relation_manager(): void
    {
        $lease = $this->lease();

        Livewire::test(InstallmentsRelationManager::class, [
            'ownerRecord' => $lease,
            'pageClass' => ViewLease::class,
        ])
            ->callAction(TestAction::make('generate_schedule')->table(), ['number_of_installments' => 6]);

        $this->assertSame(6, $lease->fresh()->installments()->count());
    }

    public function test_recording_a_payment_from_the_relation_manager(): void
    {
        $lease = $this->lease();
        $installment = app(InstallmentGenerator::class)->generateSchedule($lease, 1)->first();

        Livewire::test(InstallmentsRelationManager::class, [
            'ownerRecord' => $lease,
            'pageClass' => ViewLease::class,
        ])
            ->callAction(TestAction::make('record_payment')->table($installment), ['amount' => 60000]);

        $this->assertSame('0.00', $installment->fresh()->balance_due);
    }
}
