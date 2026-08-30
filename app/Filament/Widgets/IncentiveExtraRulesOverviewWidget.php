<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Incentive\IncentiveExtraRules\IncentiveExtraRulesResource;
use App\Models\IncentiveExtraRule;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class IncentiveExtraRulesOverviewWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return IncentiveExtraRule::query();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Extra % Rules'))
            ->defaultSort('min_count')
            ->columns([
                TextColumn::make('min_count')->label(__('Min Matters'))->sortable(),
                TextColumn::make('max_count')->label(__('Max Matters'))->placeholder(__('No limit')),
                TextColumn::make('extra_percentage')->label(__('Extra %'))->suffix('%')->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('Edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->url(fn ($record) => IncentiveExtraRulesResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make()->iconButton(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Rule'))
                    ->url(IncentiveExtraRulesResource::getUrl('create')),
            ])
            ->emptyStateHeading(__('No extra rules yet'))
            ->emptyStateDescription(__('Add rules per the PDF: 5 matters = 1.5%, 6 = 2%, >6 = 3%'));
    }
}
