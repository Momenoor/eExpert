<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpertTypeResource\Pages;
use App\Filament\Admin\Resources\ExpertTypeResource\RelationManagers;
use App\Filament\Forms\ExpertTypeForm;
use App\Models\ExpertType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpertTypeResource extends Resource
{
    protected static ?string $model = ExpertType::class;
    protected static ?string $navigationGroup = 'Experts';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(ExpertTypeForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('parent.name')->searchable(),
                Tables\Columns\TextColumn::make('experts.entity.name')
                    ->listWithLineBreaks(true)
                    ->bulleted()
                    ->searchable(),
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
            'index' => Pages\ListExpertTypes::route('/'),
            'create' => Pages\CreateExpertType::route('/create'),
            'edit' => Pages\EditExpertType::route('/{record}/edit'),
        ];
    }
}
