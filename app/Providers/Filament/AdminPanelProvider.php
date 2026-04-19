<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Pages\Auth\CustomProfile;
use App\Livewire\FontSizeSlider;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Events\FilamentActionEvent;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\FontProviders\GoogleFontProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JibayMcs\FilamentTour\FilamentTourPlugin;
use Livewire\Livewire;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use TomatoPHP\FilamentUsers\Filament\Resources\Users\Schemas\UserForm;
use TomatoPHP\FilamentUsers\Filament\Resources\Users\UserResource;
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
            ->login(CustomLogin::class)
            ->sidebarWidth('15rem')
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
                fn() => Blade::render('@livewire(\'font-size-slider\')')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(__('Finance')),
                NavigationGroup::make(__('Reports')),
                NavigationGroup::make(__('Settings')),
                NavigationGroup::make(__('Filament Shield'))

            ])
            ->authMiddleware([
                Authenticate::class,
            ])->plugins([
                //FilamentEnvEditorPlugin::make(),
                FilamentUsersPlugin::make(),
                FilamentShieldPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->timezone('Asia/Muscat')
                    ->editable()
                    ->selectable(),
                ActivityLogPlugin::make()
                    ->label('Log')
                    ->pluralLabel('Logs')
                    ->navigationGroup('System'),
                //FilamentUiSwitcherPlugin::make(),
                FilamentLanguageSwitcherPlugin::make()
                    ->locales(['en', ['code' => 'ar', 'name' => __('Arabic'), 'flag' => 'ae']]),
                FilamentTourPlugin::make()
                    ->enableCssSelector()
            ])
            ->databaseNotifications()
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

        Select::configureUsing(fn(Select $select) => $select->native(false));
        UserForm::register([
            TextInput::make('display_name')->label(__('Display name'))->required(),
            Select::make('party')->label(__('Party'))->searchable()->relationship('party', 'name'),
            Toggle::make('notify_by_whatsapp')->label(__('Notify by Whatsapp'))->visible(fn() => auth()->user()->hasAnyRole(['super-admin', 'super_admin']))->required(),
            Toggle::make('notify_by_email')->label(__('Notify by Email'))->default(true)->required(),
        ]);
        Table::configureUsing(fn(Table $table) => $table->striped()->stackedOnMobile());
        app(FilamentUserServices::class)->register([
            ActivitiesRelationManager::class,
        ]);
        FilamentTimezone::set('Asia/Muscat');
        FilamentAsset::register([
            Css::make('custom-css', asset('css/custom-css.css')),
        ]);
        FileUpload::configureUsing(fn(FileUpload $component) => $component->maxSize(1024 * 1024 * 50));

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): string {
                $user = Auth::user();

                // Default to 16 if guest or if user has no setting
                $defaultSize = 16;
                $cacheKey = 'user_font_size_' . ($user->id ?? 'guest');

                $fontSize = Cache::remember($cacheKey, now()->addDays(30), function () use ($user, $defaultSize) {
                    return $user->font_size ?? $defaultSize;
                });

                return "
            <style>
                :root {
                    --user-font-size: {$fontSize}px;
                }
                body {
                    font-size: var(--user-font-size) !important;
                }
            </style>
        ";
            }
        );

    }
}
