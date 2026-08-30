<?php

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class IncentiveSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Rates & Thresholds'))
                ->description(__('Core percentages and the monthly matter quota used by the incentive calculation.'))
                ->icon(Heroicon::AdjustmentsHorizontal)
                ->columns(2)
                ->schema([
                    TextInput::make('incentive_minimum_matters_per_month')
                        ->label(__('Minimum Matters Per Month'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(3),

                    TextInput::make('incentive_below_minimum_penalty_pct')
                        ->label(__('Below-Minimum Penalty (%)'))
                        ->suffix('%')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->default(2.0)
                        ->helperText(__('Flat percentage of the case fee, applied to every matter in a shortfall month.')),

                    TextInput::make('incentive_committee_fixed_percentage')
                        ->label(__('Committee Fixed Percentage (%)'))
                        ->suffix('%')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->default(8.0)
                        ->helperText(__('Flat rate for committee-commissioned matters, regardless of the type\'s own calculation.')),

                    TextInput::make('incentive_office_work_adjustment')
                        ->label(__('Office Work Adjustment (%)'))
                        ->suffix('%')
                        ->numeric()
                        ->step(0.01)
                        ->required()
                        ->default(2.0)
                        ->helperText(__('Added on top when a matter is flagged as office work.')),
                ]),

            Section::make(__('Deduction Toggles'))
                ->description(__('Turn individual deduction rules on or off for the next calculation run. Existing calculated lines are unaffected until recalculated.'))
                ->icon(Heroicon::AdjustmentsVertical)
                ->columns(2)
                ->schema([
                    Toggle::make('incentive_enable_first_review_deduction')
                        ->label(__('Enable First Review Deduction'))
                        ->default(true),

                    Toggle::make('incentive_enable_subsequent_review_deduction')
                        ->label(__('Enable Subsequent Review Deduction'))
                        ->default(true),

                    Toggle::make('incentive_enable_late_report_deduction')
                        ->label(__('Enable Late Final Report Deduction'))
                        ->default(true),

                    Toggle::make('incentive_enable_below_minimum_penalty')
                        ->label(__('Enable Below-Minimum Monthly Penalty'))
                        ->default(true),

                    Toggle::make('incentive_enable_court_penalty_exclusion')
                        ->label(__('Enable Court Penalty Full Exclusion'))
                        ->default(true),
                ]),
        ]);
    }
}
