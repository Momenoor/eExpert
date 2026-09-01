<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Pages\Auth\CustomProfile;
use App\Http\Middleware\CheckSystemOffline;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use TomatoPHP\FilamentUsers\Filament\Resources\Users\Schemas\UserForm;
use TomatoPHP\FilamentUsers\FilamentUsersPlugin;
use TomatoPHP\FilamentUsers\Services\FilamentUserServices;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(CustomLogin::class)
            ->sidebarWidth('17rem')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->font('Boutros MBC Dinkum', asset('fonts/Boutros.css'), provider: LocalFontProvider::class)
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo-dark.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('images/favicon.png'))
            ->profile(CustomProfile::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn () => Blade::render('@livewire(\'font-size-slider\')')
            )
            ->middleware([
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
            ])
            ->navigationGroups([
                NavigationGroup::make(__('Communication')),
                NavigationGroup::make(__('Finance')),
                NavigationGroup::make(__('Reports')),
                NavigationGroup::make(__('Settings')),
                NavigationGroup::make(__('Filament Shield')),
                NavigationGroup::make(__('System')),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->plugins([
                // FilamentEnvEditorPlugin::make(),
                //                FilamentPopupPlugin::make(),
                //                FilamentInboxPlugin::make(),
                //                FilamentCronManagerPlugin::make(),
                FilamentUsersPlugin::make()
                ->useAvatar(),
                FilamentShieldPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->timezone(config('app.timezone'))
                    ->editable()
                    ->selectable(),
                // Grouped with the rest of Settings, not its own 'System' —
                // the plugin's getNavigationGroup() evaluates this closure on
                // every request via Filament's evaluate(), so it always
                // reflects the current locale rather than baking in whichever
                // one was active if config/routes ever get cached. label()/
                // pluralLabel() are deliberately left unset: the package ships
                // its own proper Arabic translation for both
                // (filament-activity-log::activity.label /.plural_label) —
                // hardcoding 'Log'/'Logs' here only overrode that with
                // untranslated English.
                ActivityLogPlugin::make()
                    ->navigationGroup(fn () => __('Settings')),
                // FilamentUiSwitcherPlugin::make(),
                FilamentLanguageSwitcherPlugin::make()
                    ->locales(['en', ['code' => 'ar', 'name' => __('Arabic'), 'flag' => 'ae']]),
                //                FilamentTourPlugin::make()
                //                    ->enableCssSelector()
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->databaseTransactions()
            ->globalSearch(false)
            ->maxContentWidth(Width::Full);
    }

    public function boot(): void
    {
        //        Action::configureUsing(function (Action $action) {
        //            $action->after(function (Action $action, ?Model $record = null, array $data = []) {
        //                FilamentActionEvent::dispatch($action, $record, $data);
        //            });
        //        });
        //        BulkAction::configureUsing(function (BulkAction $action) {
        //            $action->after(function (BulkAction $action, ?Model $record = null, array $data = []) {
        //                FilamentActionEvent::dispatch($action, $record, $data);
        //            });
        //        });

        Select::configureUsing(fn (Select $select) => $select->native(false));
        UserForm::register([
            TextInput::make('display_name')->label(__('Display name'))->required(),
            Select::make('party')->label(__('Party'))->searchable()->relationship('party', 'name'),
            Toggle::make('notify_by_whatsapp')->label(__('Notify by Whatsapp'))->visible(fn () => auth()->user()->hasAnyRole(['super-admin', 'super_admin']))->default(fn () => (bool) Setting::get('default_notify_by_whatsapp', false))->required(),
            Toggle::make('notify_by_email')->label(__('Notify by Email'))->default(fn () => (bool) Setting::get('default_notify_by_email', true))->required(),
        ]);
        Table::configureUsing(fn (Table $table) => $table->striped()->stackedOnMobile());
        app(FilamentUserServices::class)->register([
            ActivitiesRelationManager::class,
        ]);
        FilamentTimezone::set(config('app.timezone'));
        FileUpload::configureUsing(fn (FileUpload $component) => $component->maxSize(1024 * 1024 * 50));

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            function (): View {
                if (! Setting::get('show_system_announcement', false)) {
                    return view('blank');
                }

                $announcement = Setting::get('system_announcement');
                if (empty($announcement)) {
                    return view('blank');
                }

                // Passed raw — the view's {{ }} escapes it once. Escaping here as
                // well produced double-escaped output (&amp;amp;) for users.
                return view('filament.pages.announcement', ['announcement' => $announcement]);
            });
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Blade::render("@livewire('notification-poller')")
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): string {
                $user = Auth::user();

                // Default to 16 if guest or if user has no setting
                $defaultSize = 16;
                $cacheKey = 'user_font_size_'.($user?->id ?? 'guest');

                $fontSize = Cache::remember($cacheKey, now()->addDays(30), function () use ($user, $defaultSize) {
                    return $user?->font_size ?? $defaultSize;
                });

                // Tailwind's sizing scale is almost entirely rem-based (relative
                // to the ROOT element's font-size, not body's) — the font-size
                // must be applied to :root itself, and applied here in the
                // server-rendered <head> so it's correct from the very first
                // paint. A separate client-side script applying it later (e.g.
                // on DOMContentLoaded) causes a visible flash-then-resize.
                // No !important needed: nothing else sets font-size on :root, and
                // the previous one forced a matching !important in the panel's
                // stylesheet to compensate.
                return "
            <style>
                :root {
                    --user-font-size: {$fontSize}px;
                    font-size: var(--user-font-size);
                }
                body {
                    font-size: var(--user-font-size);
                }
            </style>
        ";
            }
        );

    }
}
