<?php

namespace App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\RelationManagers;

use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Schemas\MatterTypeIncentiveConfigForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IncentiveConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'incentiveConfig';

    protected static ?string $recordTitleAttribute = 'calculation_type';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Incentive Config');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Incentive Configs');
    }

    public static function getModelLabel(): string
    {
        return __('Incentive Config');
    }

    public function form(Schema $schema): Schema
    {
        return MatterTypeIncentiveConfigForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calculation_type')
                    ->label(__('Calculation Type'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'tiered' => __('Tiered'),
                        'fixed' => __('Fixed'),
                        'committee' => __('Committee'),
                        default => $state,
                    }),
                TextColumn::make('assistant_rate_type')
                    ->label(__('Assistant Rate'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'percentage' => __('Percentage'),
                        'fixed' => __('Fixed Amount'),
                        default => $state,
                    }),
                TextColumn::make('assistant_rate_value')
                    ->label(__('Value')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->incentiveConfig === null),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
