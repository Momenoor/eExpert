<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\IncentiveMetaAdjustmentResource;
use App\Models\IncentiveMetaAdjustment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class IncentiveMetaAdjustmentsOverviewWidget extends TableWidget
{
    /**
     * These widgets expose incentive configuration, which is payroll data.
     * Without this they rendered for anyone who could reach the page.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:IncentiveMetaAdjustment') ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return IncentiveMetaAdjustment::query();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Meta Adjustments'))
            ->columns([
                TextColumn::make('field_name')->label(__('Field Name'))->searchable()->sortable(),
                TextColumn::make('field_value')->label(__('Field Value'))->placeholder(__('Any value'))->searchable()->sortable(),
                TextColumn::make('percentage_adjustment')->label(__('Adjustment %'))->suffix('%')->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('Edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->url(fn ($record) => IncentiveMetaAdjustmentResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make()->iconButton(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Adjustment'))
                    ->url(IncentiveMetaAdjustmentResource::getUrl('create')),
            ])
            ->emptyStateHeading(__('No meta adjustments yet'));
    }
}
