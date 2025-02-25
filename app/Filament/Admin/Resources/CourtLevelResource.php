<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CourtLevelResource\Pages;
use App\Filament\Admin\Resources\CourtLevelResource\RelationManagers;
use App\Filament\Forms\CourtLevelForm;
use App\Models\CourtLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourtLevelResource extends Resource
{
    protected static ?string $model = CourtLevel::class;

    protected static ?string $navigationGroup = 'Courts';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...CourtLevelForm::make()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('courts.entity.name')
                    ->label('Courts')
                    ->searchable()
                    ->listWithLineBreaks()
                    ->badge(),
                Tables\Columns\TextColumn::make('matters_count')->counts('matters')
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
            'index' => Pages\ListCourtLevels::route('/'),
            'create' => Pages\CreateCourtLevel::route('/create'),
            'edit' => Pages\EditCourtLevel::route('/{record}/edit'),
        ];
    }
}
