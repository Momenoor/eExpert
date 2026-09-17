<?php

namespace Tests\Feature\PMS;

use App\Filament\Pms\Pages\PMSSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PMSSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        Filament::setCurrentPanel(Filament::getPanel('pms'));
    }

    public function test_the_page_prefills_from_code_defaults_when_nothing_is_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        Livewire::test(PMSSettings::class)
            ->assertSchemaStateSet([
                'pms_mixed_use_vat_rate' => 0.05,
                'pms_attestation_fee_estimate' => 0,
                'pms_bounced_cheque_penalty' => 100,
            ], 'form');
    }

    public function test_saving_settings_persists_and_is_picked_up_by_setting_get(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        Livewire::test(PMSSettings::class)
            ->fillForm([
                'pms_mixed_use_vat_rate' => 0.025,
                'pms_attestation_fee_estimate' => 500,
                'pms_bounced_cheque_penalty' => 200,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(0.025, (float) Setting::get('pms_mixed_use_vat_rate'), 0.0001);
        $this->assertEqualsWithDelta(200.0, (float) Setting::get('pms_bounced_cheque_penalty'), 0.001);
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        $this->get(PMSSettings::getUrl())->assertForbidden();
    }
}
