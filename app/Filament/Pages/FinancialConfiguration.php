<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\IncentiveSettingsForm;
use App\Filament\Pages\Schemas\PayrollSettingsForm;
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

/**
 * Incentive Configuration and Payroll Configuration merged into one page,
 * split into an "Incentive" cluster and a "Payroll" cluster — each keeps its
 * own schema, statePath and save handler, they only now share one nav entry
 * and one URL. A user sees whichever cluster(s) their permission covers;
 * canAccess() admits anyone holding either one.
 */
class FinancialConfiguration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 7;

    public ?array $incentiveData = [];

    public ?array $payrollData = [];

    public static function getNavigationLabel(): string
    {
        return __('Financial Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial');
    }

    public function getTitle(): string
    {
        return __('Financial Settings');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('View:FinancialConfiguration') ?? false;
    }

    protected function canViewIncentiveCluster(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // If user has specific resource permission or has super-admin/FinancialConfiguration
        return $user->can('ViewAny:MatterTypeIncentiveConfig')
            || $user->can('ViewAny:IncentiveExtraRule')
            || (! $user->can('ViewAny:PayrollRun') && ! $user->can('ViewAny:EmployeeProfile') && $user->can('View:FinancialConfiguration'));
    }

    protected function canViewPayrollCluster(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:PayrollRun')
            || $user->can('ViewAny:EmployeeProfile')
            || (! $user->can('ViewAny:MatterTypeIncentiveConfig') && ! $user->can('ViewAny:IncentiveExtraRule') && $user->can('View:FinancialConfiguration'));
    }

    public function mount(): void
    {
        if ($this->canViewIncentiveCluster()) {
            $this->incentiveForm->fill([
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

        if ($this->canViewPayrollCluster()) {
            $this->payrollForm->fill([
                'payroll_loan_rounding_step' => Setting::get('payroll_loan_rounding_step', 50),

                'payroll_days_per_month' => Setting::get('payroll_days_per_month', 30),
                'payroll_eosg_cap_months' => Setting::get('payroll_eosg_cap_months', 24),
                'payroll_eosg_days_per_year_first_five' => Setting::get('payroll_eosg_days_per_year_first_five', 21),
                'payroll_eosg_days_per_year_after_five' => Setting::get('payroll_eosg_days_per_year_after_five', 30),

                'payroll_annual_leave_days' => Setting::get('payroll_annual_leave_days', 30),
                'payroll_annual_leave_days_per_month' => Setting::get('payroll_annual_leave_days_per_month', 2),

                'payroll_sick_full_pay_days' => Setting::get('payroll_sick_full_pay_days', 15),
                'payroll_sick_half_pay_days' => Setting::get('payroll_sick_half_pay_days', 30),
                'payroll_sick_unpaid_days' => Setting::get('payroll_sick_unpaid_days', 45),
            ]);
        }
    }

    public function incentiveForm(Schema $schema): Schema
    {
        return IncentiveSettingsForm::configure($schema)
            ->statePath('incentiveData');
    }

    public function payrollForm(Schema $schema): Schema
    {
        return PayrollSettingsForm::configure($schema)
            ->statePath('payrollData');
    }

    public function content(Schema $schema): Schema
    {
        $clusters = [];

        if ($this->canViewIncentiveCluster()) {
            $clusters[] = Tabs\Tab::make(__('Incentive'))
                ->icon(Heroicon::AdjustmentsHorizontal)
                ->schema([
                    Tabs::make('incentive-configuration-tabs')
                        ->contained(false)
                        ->tabs([
                            Tabs\Tab::make(__('Rates & Deductions'))
                                ->icon(Heroicon::AdjustmentsHorizontal)
                                ->schema([
                                    Form::make([
                                        EmbeddedSchema::make('incentiveForm'),
                                    ])
                                        ->id('incentiveForm')
                                        ->livewireSubmitHandler('saveIncentive')
                                        ->footer([
                                            Actions::make($this->getIncentiveFormActions())
                                                ->key('incentive-form-actions'),
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

        if ($this->canViewPayrollCluster()) {
            $clusters[] = Tabs\Tab::make(__('Payroll'))
                ->icon(Heroicon::Calculator)
                ->schema([
                    Form::make([
                        EmbeddedSchema::make('payrollForm'),
                    ])
                        ->id('payrollForm')
                        ->livewireSubmitHandler('savePayroll')
                        ->footer([
                            Actions::make($this->getPayrollFormActions())
                                ->key('payroll-form-actions'),
                        ]),
                ]);
        }

        return $schema->components([
            Tabs::make('financial-configuration-clusters')
                ->contained(false)
                ->tabs($clusters),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getIncentiveFormActions(): array
    {
        return [
            Action::make('saveIncentive')
                ->label(__('Save Settings'))
                ->submit('saveIncentive')
                ->icon(Heroicon::Check)
                ->color('primary')
                ->keyBindings(['mod+s']),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getPayrollFormActions(): array
    {
        return [
            Action::make('savePayroll')
                ->label(__('Save Settings'))
                ->submit('savePayroll')
                ->icon(Heroicon::Check)
                ->color('primary')
                ->keyBindings(['mod+s']),
        ];
    }

    public function saveIncentive(): void
    {
        $state = $this->incentiveForm->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, $value, 'incentive');
        }

        Notification::make()
            ->title(__('Settings saved successfully'))
            ->success()
            ->send();
    }

    public function savePayroll(): void
    {
        $state = $this->payrollForm->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, $value, 'payroll');
        }

        Notification::make()
            ->title(__('Settings saved successfully'))
            ->success()
            ->send();
    }
}
