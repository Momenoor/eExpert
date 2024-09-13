<?php

namespace App\Filament\Admin\Resources;

use App\Enum\ActiveStatusEnum;
use App\Filament\Resources\MatterTypeResource\Pages;
use App\Filament\Resources\MatterTypeResource\RelationManagers;
use App\Models\MatterType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MatterTypeResource extends Resource
{
    protected static ?string $model = MatterType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignorable: fn($record) => $record)
                    ->columnSpanFull()
                    ->maxLength(191),
                Forms\Components\ToggleButtons::make('status')
                    ->columnSpanFull()
                    ->options(ActiveStatusEnum::class)
                    ->default(ActiveStatusEnum::Active->value)
                    ->grouped(),


            ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ActiveStatusEnum::class)
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\MatterTypeResource\Pages\ListMatterTypes::route('/'),
            'create' => \App\Filament\Admin\Resources\MatterTypeResource\Pages\CreateMatterType::route('/create'),
            'view' => \App\Filament\Admin\Resources\MatterTypeResource\Pages\ViewMatterType::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\MatterTypeResource\Pages\EditMatterType::route('/{record}/edit'),
        ];
    }
}
