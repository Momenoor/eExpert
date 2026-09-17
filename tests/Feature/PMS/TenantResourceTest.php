<?php

namespace Tests\Feature\PMS;

use App\Filament\Pms\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Pms\Resources\Tenants\Pages\EditTenant;
use App\Models\Party;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The tenant form creates the Party (tagged 'tenant') and the Tenant
 * together in one action — a tenant being onboarded here has, in the
 * overwhelming majority of cases, no existing Party to pick from yet.
 */
class TenantResourceTest extends TestCase
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

    public function test_creating_a_tenant_creates_both_the_party_and_the_profile(): void
    {
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Acme Trading LLC',
                'phone' => ['0501234567'],
                'email' => ['tenant@example.com'],
                'tenant_type' => 'company',
                'identification_type' => 'trade_license',
                'identification_number' => 'CN-1234567',
                'trn' => '100123456700003',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $party = Party::where('name', 'Acme Trading LLC')->sole();
        $this->assertTrue($party->isTenant());
        $this->assertSame(['0501234567'], $party->phone);

        $profile = Tenant::where('party_id', $party->id)->sole();
        $this->assertTrue($profile->isCompany());
        $this->assertSame('CN-1234567', $profile->identification_number);
    }

    public function test_editing_a_tenant_updates_both_the_party_and_the_profile(): void
    {
        $party = Party::factory()->tenant()->create(['name' => 'Old Name']);
        $profile = Tenant::create([
            'party_id' => $party->id,
            'tenant_type' => 'person',
            'identification_type' => 'emirates_id',
            'identification_number' => '784-1990-1234567-1',
        ]);

        Livewire::test(EditTenant::class, ['record' => $profile->getKey()])
            ->fillForm([
                'name' => 'New Name',
                'identification_number' => '784-1990-7654321-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Name', $party->fresh()->name);
        $this->assertSame('784-1990-7654321-1', $profile->fresh()->identification_number);
    }
}
