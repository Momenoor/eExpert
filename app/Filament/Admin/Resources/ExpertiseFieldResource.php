<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpertiseFieldResource\Pages;
use App\Filament\Admin\Resources\ExpertiseFieldResource\RelationManagers;
use App\Filament\Forms\ExpertiseFieldForm;
use App\Models\ExpertiseField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpertiseFieldResource extends Resource
{
    protected static ?string $model = ExpertiseField::class;
    protected static ?string $navigationGroup = 'Experts';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...ExpertiseFieldForm::make(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('experts.entity.name')
                    ->searchable()
                    ->listWithLineBreaks()
                    ->bulleted(),
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
            'index' => Pages\ListExpertiseFields::route('/'),
            'create' => Pages\CreateExpertiseField::route('/create'),
            'edit' => Pages\EditExpertiseField::route('/{record}/edit'),
        ];
    }
}
