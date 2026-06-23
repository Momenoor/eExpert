<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Schemas;

use App\Enums\MatterDifficulty;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class IncentiveCalculationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Calculation Details'))->schema([
                    TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->color(fn ($state) => $state === 'finalized' ? 'success' : 'warning')
                        ->formatStateUsing(fn ($state) => $state === 'finalized' ? __('Finalized') : __('Draft')),
                    TextEntry::make('period_start')->label(__('Period Start'))->date(),
                    TextEntry::make('period_end')->label(__('Period End'))->date(),
                    TextEntry::make('finalized_at')->label(__('Finalized At'))->dateTime()->placeholder(__('Not finalized yet')),
                    TextEntry::make('createdBy.name')->label(__('Created By')),
                    TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                ])->columns(3),

                Section::make(__('Assistant Summary'))
                    ->description(__('Per-assistant totals including extra % and minimum penalties'))
                    ->schema([
                        RepeatableEntry::make('assistantExtras')->label(__('Assistant extras'))->schema([
                            TextEntry::make('party.name')->label(__('Assistant')),
                            TextEntry::make('completed_matter_count')->label(__('Completed Matters'))
                                ->badge()->color(fn ($state) => $state >= 6 ? 'success' : 'danger'),
                            TextEntry::make('meets_minimum')->label(__('Meets Minimum (6)'))->badge()
                                ->color(fn ($state) => $state ? 'success' : 'danger')
                                ->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No')),
                            TextEntry::make('extra_percentage')->label(__('Extra %'))->suffix('%')->placeholder('0%'),
                            TextEntry::make('extra_amount')->label(__('Extra Amount'))->money('AED')->placeholder('—'),
                            TextEntry::make('minimum_penalty_pct')->label(__('Penalty %'))->suffix('%')
                                ->color('danger')->placeholder('0%'),
                            TextEntry::make('penalty_amount')->label(__('Penalty Amount'))->money('AED')
                                ->color('danger')->placeholder('—'),
                        ])->columns(7),
                    ]),

                Section::make(__('Calculation Lines'))
                    ->columnSpanFull()
                    ->description(__('One line per matter per paid fee'))
                    ->schema([
                        RepeatableEntry::make('lines')->label(__('Lines'))->schema([
                            TextEntry::make('matter.reference')->label(__('Matter'))->url(fn($record) => route('filament.admin.resources.matters.view', $record->matter_id))->openUrlInNewTab(),

                            TextEntry::make('matter.difficulty')->label(__('Difficulty'))->badge(),
                            TextEntry::make('completion_days')->label(__('Working Days'))->placeholder(__('Fixed')),
                            TextEntry::make('fee_amount_excl_vat')->label(__('Fee (excl. VAT)'))->money('AED'),
                            TextEntry::make('effective_percentage')->label(__('Rate %'))->suffix('%')
                                ->placeholder(fn ($record) => $record->committee_adjustment != 0
                                    ? __('Base').': '.$record->base_percentage.'% '
                                    .($record->committee_adjustment > 0 ? '+' : '').$record->committee_adjustment.'%'
                                    : null
                                ),
                            TextEntry::make('base_amount')->label(__('Base Amount'))->money('AED'),
                            TextEntry::make('total_deduction_pct')->label(__('Deductions %'))->suffix('%')
                                ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')->placeholder('0%'),
                            TextEntry::make('net_amount')->label(__('Net Amount'))->money('AED')
                                ->weight(FontWeight::Bold),
                        ])->columns(8),
                    ]),
            ]);
    }
}
