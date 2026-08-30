<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\CustomLogin;
use App\Http\Middleware\CheckSystemOffline;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_set_and_get_setting(): void
    {
        Setting::set('test_key', 'test_value', 'general');

        $this->assertTrue(Setting::has('test_key'));
        $this->assertSame('test_value', Setting::get('test_key'));
    }

    public function test_setting_casts_types_correctly(): void
    {
        Setting::set('bool_setting', true);
        Setting::set('int_setting', 42);
        Setting::set('array_setting', ['a' => 1, 'b' => 2]);

        $this->assertTrue(Setting::get('bool_setting'));
        $this->assertSame(42, Setting::get('int_setting'));
        $this->assertSame(['a' => 1, 'b' => 2], Setting::get('array_setting'));
    }

    public function test_setting_can_be_forgotten(): void
    {
        Setting::set('temporary', 'val');
        $this->assertTrue(Setting::has('temporary'));

        Setting::forget('temporary');
        $this->assertFalse(Setting::has('temporary'));
        $this->assertNull(Setting::get('temporary'));
    }

    public function test_setting_offline_helpers(): void
    {
        $this->assertFalse(Setting::isOffline());

        Setting::set('app_offline', true);
        $this->assertTrue(Setting::isOffline());

        Setting::set('offline_message', 'Under maintenance until 5 PM');
        $this->assertSame('Under maintenance until 5 PM', Setting::getOfflineMessage());
    }

    public function test_setting_applies_mail_configuration(): void
    {
        Setting::set('mail_mailer', 'smtp');
        Setting::set('mail_host', 'smtp.custom-server.com');
        Setting::set('mail_port', 2525);
        Setting::set('mail_username', 'testuser');
        Setting::set('mail_password', 'secret123');
        Setting::set('mail_encryption', 'tls');
        Setting::set('mail_from_address', 'admin@example.com');
        Setting::set('mail_from_name', 'Admin Tester');

        Setting::applyMailConfig();

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('smtp.custom-server.com', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(2525, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('testuser', Config::get('mail.mailers.smtp.username'));
        $this->assertSame('secret123', Config::get('mail.mailers.smtp.password'));
        $this->assertSame('tls', Config::get('mail.mailers.smtp.encryption'));
        $this->assertSame('admin@example.com', Config::get('mail.from.address'));
        $this->assertSame('Admin Tester', Config::get('mail.from.name'));
    }

    public function test_offline_middleware_blocks_guests_when_offline(): void
    {
        Setting::set('app_offline', true);
        Setting::set('offline_message', 'Maintenance in progress');

        $middleware = new CheckSystemOffline;

        // Web request redirects to system-down
        $request = Request::create('/dashboard', 'GET');
        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });
        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect(route('system-down')));

        // Admin request redirects guest to login page
        $adminRequest = Request::create('/admin', 'GET');
        $adminResponse = $middleware->handle($adminRequest, function () {
            return new Response('OK', 200);
        });
        $this->assertSame(302, $adminResponse->getStatusCode());
        $this->assertTrue($adminResponse->isRedirect(route('filament.admin.auth.login')));

        // JSON request returns 503
        $jsonRequest = Request::create('/api/data', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $jsonResponse = $middleware->handle($jsonRequest, function () {
            return new Response('OK', 200);
        });
        $this->assertSame(503, $jsonResponse->getStatusCode());
    }

    public function test_offline_middleware_allows_admins_when_offline(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Setting::set('app_offline', true);
        Setting::set('offline_allow_admins', true);

        $middleware = new CheckSystemOffline;
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function test_offline_middleware_allows_plain_admin_role_when_offline(): void
    {
        // Regression test: the 'admin' role (as opposed to 'super_admin'/'super-admin')
        // was previously not recognized by the offline check, so a logged-in user
        // whose only role is 'admin' was bounced to the maintenance page after login.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Setting::set('app_offline', true);
        Setting::set('offline_allow_admins', true);

        $middleware = new CheckSystemOffline;
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function test_offline_middleware_allows_all_requests_when_online(): void
    {
        Setting::set('app_offline', false);

        $middleware = new CheckSystemOffline;
        $request = Request::create('/dashboard', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_offline_middleware_allows_login_and_livewire_routes_when_offline(): void
    {
        Setting::set('app_offline', true);

        $middleware = new CheckSystemOffline;

        // Login page request
        $loginRequest = Request::create('/admin/login', 'GET');
        $loginResponse = $middleware->handle($loginRequest, fn () => new Response('Login Form', 200));
        $this->assertSame(200, $loginResponse->getStatusCode());

        // Livewire update POST request (used by Filament login submission)
        $livewireRequest = Request::create('/livewire/update', 'POST');
        $livewireResponse = $middleware->handle($livewireRequest, fn () => new Response('{"effects":{}}', 200));
        $this->assertSame(200, $livewireResponse->getStatusCode());

        // System down page request
        $systemDownRequest = Request::create('/system-down', 'GET');
        $systemDownResponse = $middleware->handle($systemDownRequest, fn () => new Response('Maintenance Page', 200));
        $this->assertSame(200, $systemDownResponse->getStatusCode());
    }

    public function test_can_simulate_filament_login_when_offline(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'admin@domain.com',
            'name' => 'adminuser',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('super_admin');

        Setting::set('app_offline', true);
        Setting::set('offline_allow_admins', true);

        // Can access login page
        $this->get('/admin/login')->assertSuccessful();

        // Can authenticate using Livewire CustomLogin component
        Livewire::test(CustomLogin::class)
            ->fillForm([
                'login' => 'admin@domain.com',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);

        // Admin can now access admin panel
        $this->get('/admin')->assertSuccessful();
    }

    public function test_can_access_login_page_via_login_route_when_offline(): void
    {
        Setting::set('app_offline', true);

        // Visiting /login redirects to /admin/login
        $this->get('/login')->assertRedirect(route('filament.admin.auth.login'));

        // Visiting /admin/login is successful
        $this->get('/admin/login')->assertSuccessful();

        // Visiting /admin as guest redirects to /admin/login
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_authenticated_non_admin_can_recover_via_maintenance_page_logout(): void
    {
        // Regression: Filament's own login page redirects an already-authenticated
        // user straight to the dashboard, which the offline check then bounces to
        // system-down for a non-admin — so they never see the login form again and
        // appear permanently locked out. The maintenance page must offer a way out.
        Role::firstOrCreate(['name' => 'assistant', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('assistant');

        Setting::set('app_offline', true);
        Setting::set('offline_allow_admins', true);

        // Hitting /admin/login while already authenticated as a non-admin loops to system-down.
        $response = $this->actingAs($user)->get('/admin/login');
        $response->assertRedirect('/admin');
        $this->followingRedirects()->get('/admin/login');

        // The maintenance page must offer a sign-out control for the stuck session.
        $this->actingAs($user)
            ->get(route('system-down'))
            ->assertSuccessful()
            ->assertSee(route('filament.admin.auth.logout'), false);

        // Signing out from there must succeed even while offline...
        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect();

        $this->assertGuest();

        // ...and now the real login form is reachable again.
        $this->get('/admin/login')->assertSuccessful();
    }

    public function test_settings_use_runtime_memoization_to_prevent_duplicate_queries(): void
    {
        Setting::set('test_runtime_key', 'value123');

        // Warm up / populate cache
        Setting::get('test_runtime_key');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Repeated calls in the same request should hit the static in-memory cache
        for ($i = 0; $i < 10; $i++) {
            $value = Setting::get('test_runtime_key');
            $this->assertSame('value123', $value);
        }

        $this->assertSame(0, $queryCount);
    }

    public function test_system_down_page_displays_offline_message_from_database(): void
    {
        Setting::set('offline_message', 'Custom maintenance message from DB');

        $response = $this->get(route('system-down'));

        $response->assertSuccessful();
        $response->assertSee('Custom maintenance message from DB');
    }
}
