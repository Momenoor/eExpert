<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\MatterTypeIncentiveConfigResource;
use App\Models\MatterTypeIncentiveConfig;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class IncentiveTypeConfigsOverviewWidget extends TableWidget
{
    /**
     * These widgets expose incentive configuration, which is payroll data.
     * Without this they rendered for anyone who could reach the page.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:MatterTypeIncentiveConfig') ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return MatterTypeIncentiveConfig::query()->withCount('tiers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Type Configurations'))
            ->columns([
                TextColumn::make('matterTypes.name')->label(__('Matter Type'))->listWithLineBreaks()->limitList(20),
                TextColumn::make('calculation_type')->label(__('Type'))->badge()
                    ->color(fn ($state) => match ($state) {
                        'fixed' => 'success', 'tiered' => 'info', 'committee' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed' => __('Fixed'), 'tiered' => __('Tiered'), 'committee' => __('Committee'), default => $state,
                    }),
                TextColumn::make('fixed_percentage')->label(__('Fixed %'))->suffix('%')->placeholder('—'),
                TextColumn::make('assistant_rate')->label(__('Assistant Rate'))->suffix('%'),
                TextColumn::make('tiers_count')->label(__('Tiers')),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->url(fn ($record) => MatterTypeIncentiveConfigResource::getUrl('view', ['record' => $record])),
                Action::make('edit')
                    ->label(__('Edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->url(fn ($record) => MatterTypeIncentiveConfigResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Configuration'))
                    ->url(MatterTypeIncentiveConfigResource::getUrl('create')),
            ])
            ->emptyStateHeading(__('No type configurations yet'));
    }
}
