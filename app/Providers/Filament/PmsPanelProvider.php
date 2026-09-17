<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Filament\Mms\Pages\Auth\CustomLogin;
use App\Filament\Mms\Pages\Auth\CustomProfile;
use App\Filament\Mms\Support\SystemSwitcher;
use App\Filament\Pms\Pages\PmsDashboard;
use App\Filament\Pms\Pages\PMSSettings;
use App\Http\Middleware\CheckSystemOffline;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\TrackCurrentSystem;
use App\Http\Middleware\TrackUserLastSeen;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use TomatoPHP\FilamentUsers\FilamentUsersPlugin;

/**
 * The Properties Management System panel — a separate Filament panel (not
 * multi-tenancy: PMS/MMS are feature areas of one app, not separate tenant
 * organizations) sharing the same login/session as the `admin` (MMS) panel.
 * Explicitly registers only the PMS resources/pages so its sidebar never
 * shows MMS content, without needing per-resource visibility overrides.
 * Access is gated in User::canAccessPanel() via the Access:MultipleSystems
 * permission.
 */
class PmsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pms')
            ->path('pms')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(CustomLogin::class)
            ->sidebarWidth('17rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->font('Boutros MBC Dinkum', asset('fonts/Boutros.css'), provider: LocalFontProvider::class)
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo-dark.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('images/favicon.png'))
            ->profile(CustomProfile::class)
            ->discoverResources(in: app_path('Filament/Pms/Resources'), for: 'App\Filament\Pms\Resources')
            ->discoverPages(in: app_path('Filament/Pms/Pages'), for: 'App\Filament\Pms\Pages')
            ->discoverClusters(in: app_path('Filament/Pms/Clusters'), for: 'App\Filament\Pms\Clusters')
            ->discoverWidgets(in: app_path('Filament/Pms/Widgets'), for: 'App\Filament\Pms\Widgets')
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn () => Blade::render('@livewire(\'font-size-slider\')')
            )
            ->pages([
                PmsDashboard::class,
                PMSSettings::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('Properties')),
                NavigationGroup::make(fn () => __('Leasing')),
                NavigationGroup::make(fn () => __('Ownership')),
                NavigationGroup::make(fn () => __('Settings')),
                NavigationGroup::make(fn () => __('filament-shield::filament-shield.nav.group')),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => SystemSwitcher::render()
            )
            ->middleware([
                // Same ordering/rationale as MmsPanelProvider — this
                // panel's middleware list runs independently of the app's
                // `web` group.
                RedirectToInstaller::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                CheckSystemOffline::class,
                TrackUserLastSeen::class,
                TrackCurrentSystem::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                // Required — Shield's per-panel widget/page permission
                // discovery needs this registered here too, or canAccess()
                // calls on PMS widgets/pages throw BadMethodCallException.

                FilamentShieldPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->timezone(config('app.timezone'))
                    ->editable()
                    ->selectable(),
                ActivityLogPlugin::make()
                    ->navigationGroup(fn () => __('Settings'))
                    ->navigationSort(99),
                // FilamentUiSwitcherPlugin::make(),
                FilamentLanguageSwitcherPlugin::make()
                    ->locales(['en', ['code' => 'ar', 'name' => __('Arabic'), 'flag' => 'ae']]),
                FilamentUsersPlugin::make()
                    ->useAvatar(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->databaseTransactions()
            ->globalSearch(false)
            ->maxContentWidth(Width::Full);
    }
}
