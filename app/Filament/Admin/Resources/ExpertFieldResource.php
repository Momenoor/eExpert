<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpertFieldResource\Pages;
use App\Filament\Admin\Resources\ExpertFieldResource\RelationManagers;
use App\Models\ExpertField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpertFieldResource extends Resource
{
    protected static ?string $model = ExpertField::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignorable: fn($record) => $record),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListExpertFields::route('/'),
            'create' => Pages\CreateExpertField::route('/create'),
            'edit' => Pages\EditExpertField::route('/{record}/edit'),
        ];
    }
}
