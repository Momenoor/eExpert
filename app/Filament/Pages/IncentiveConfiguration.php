<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\IncentiveSettingsForm;
use App\Filament\Widgets\IncentiveExtraRulesOverviewWidget;
use App\Filament\Widgets\IncentiveMetaAdjustmentsOverviewWidget;
use App\Filament\Widgets\IncentiveTypeConfigsOverviewWidget;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class IncentiveConfiguration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Incentive Configuration');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public function getTitle(): string
    {
        return __('Incentive Configuration');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['super-admin', 'super_admin']);
        }

        return (bool) ($user->is_admin ?? false);
    }

    public function mount(): void
    {
        $this->form->fill([
            'incentive_minimum_matters_per_month' => Setting::get('incentive_minimum_matters_per_month', 3),
            'incentive_below_minimum_penalty_pct' => Setting::get('incentive_below_minimum_penalty_pct', 2.0),
            'incentive_committee_fixed_percentage' => Setting::get('incentive_committee_fixed_percentage', 8.0),
            'incentive_office_work_adjustment' => Setting::get('incentive_office_work_adjustment', 2.0),

            'incentive_enable_first_review_deduction' => Setting::get('incentive_enable_first_review_deduction', true),
            'incentive_enable_subsequent_review_deduction' => Setting::get('incentive_enable_subsequent_review_deduction', true),
            'incentive_enable_late_report_deduction' => Setting::get('incentive_enable_late_report_deduction', true),
            'incentive_enable_below_minimum_penalty' => Setting::get('incentive_enable_below_minimum_penalty', true),
            'incentive_enable_court_penalty_exclusion' => Setting::get('incentive_enable_court_penalty_exclusion', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return IncentiveSettingsForm::configure($schema)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('incentive-configuration-tabs')
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make(__('Rates & Deductions'))
                            ->icon(Heroicon::AdjustmentsHorizontal)
                            ->schema([
                                Form::make([
                                    EmbeddedSchema::make('form'),
                                ])
                                    ->id('form')
                                    ->livewireSubmitHandler('save')
                                    ->footer([
                                        Actions::make($this->getFormActions())
                                            ->key('form-actions'),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('Type Configurations'))
                            ->icon(Heroicon::Cog6Tooth)
                            ->schema([
                                Livewire::make(IncentiveTypeConfigsOverviewWidget::class),
                            ]),

                        Tabs\Tab::make(__('Extra % Rules'))
                            ->icon(Heroicon::PlusCircle)
                            ->schema([
                                Livewire::make(IncentiveExtraRulesOverviewWidget::class),
                            ]),

                        Tabs\Tab::make(__('Meta Adjustments'))
                            ->icon(Heroicon::PuzzlePiece)
                            ->schema([
                                Livewire::make(IncentiveMetaAdjustmentsOverviewWidget::class),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Settings'))
                ->submit('save')
                ->icon(Heroicon::Check)
                ->color('primary')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, $value, 'incentive');
        }

        Notification::make()
            ->title(__('Settings saved successfully'))
            ->success()
            ->send();
    }
}
