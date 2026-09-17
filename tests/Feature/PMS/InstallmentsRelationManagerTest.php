<?php

namespace Tests\Feature\PMS;

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\RelationManagers\InstallmentsRelationManager;
use App\Models\Contract;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContractService;
use App\Services\InstallmentGenerator;
use Filament\Actions\Testing\TestAction;
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
    }

    private function contract(): Contract
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(ContractService::class)->createFromRawInputs([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 60000,
        ], [
            ['party_id' => $tenant->id, 'role' => 'primary_tenant'],
        ], [$unit->id]);
    }

    public function test_generating_a_schedule_from_the_relation_manager(): void
    {
        $contract = $this->contract();

        Livewire::test(InstallmentsRelationManager::class, [
            'ownerRecord' => $contract,
            'pageClass' => ViewContract::class,
        ])
            ->callAction(TestAction::make('generate_schedule')->table(), ['number_of_installments' => 6]);

        $this->assertSame(6, $contract->fresh()->installments()->count());
    }

    public function test_recording_a_payment_from_the_relation_manager(): void
    {
        $contract = $this->contract();
        $installment = app(InstallmentGenerator::class)->generateSchedule($contract, 1)->first();

        Livewire::test(InstallmentsRelationManager::class, [
            'ownerRecord' => $contract,
            'pageClass' => ViewContract::class,
        ])
            ->callAction(TestAction::make('record_payment')->table($installment), ['amount' => 60000]);

        $this->assertSame('0.00', $installment->fresh()->balance_due);
    }
}
