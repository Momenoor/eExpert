<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\SystemSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');
    }

    public function test_can_render_system_settings_page(): void
    {
        $this->get(SystemSettings::getUrl())->assertSuccessful();
    }

    public function test_guest_cannot_access_system_settings_page(): void
    {
        auth()->logout();

        $this->get(SystemSettings::getUrl())->assertRedirect();
    }

    public function test_can_fill_and_save_settings(): void
    {
        Livewire::test(SystemSettings::class)
            ->fillForm([
                'app_name' => 'My Custom System',
                'company_name' => 'Custom Company',
                'currency_code' => 'USD',
                'app_locale' => 'en',
                'app_offline' => true,
                'offline_message' => 'Custom Offline Message',
                'mail_mailer' => 'log',
                'mail_from_address' => 'system@test.com',
                'mail_from_name' => 'System Tester',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('My Custom System', Setting::get('app_name'));
        $this->assertSame('Custom Company', Setting::get('company_name'));
        $this->assertSame('USD', Setting::get('currency_code'));
        $this->assertSame('en', Setting::get('app_locale'));
        $this->assertTrue(Setting::get('app_offline'));
        $this->assertSame('Custom Offline Message', Setting::get('offline_message'));
        $this->assertSame('log', Setting::get('mail_mailer'));
        $this->assertSame('system@test.com', Setting::get('mail_from_address'));
        $this->assertSame('System Tester', Setting::get('mail_from_name'));
    }

    public function test_can_trigger_send_test_email(): void
    {
        Mail::fake();

        Livewire::test(SystemSettings::class)
            ->callAction('sendTestEmail', [
                'recipient' => 'target@example.com',
            ])
            ->assertHasNoErrors();
    }
}
