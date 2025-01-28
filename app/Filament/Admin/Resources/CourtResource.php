<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CourtResource\Pages;
use App\Filament\Admin\Resources\CourtResource\RelationManagers;
use App\Filament\Forms\CourtForm;
use App\Models\Court;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourtResource extends Resource
{
    protected static ?string $model = Court::class;
    protected static ?string $navigationGroup = 'Courts';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                CourtForm::make()
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('entity.name')->label('name'),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('entity.email')->icon('heroicon-o-envelope'),
                        Tables\Columns\TextColumn::make('entity.mobile')->icon('heroicon-o-phone'),
                    ])->space(),
                    Tables\Columns\TextColumn::make('entity.address')->label('address')->limit(50),
                    Tables\Columns\TextColumn::make('entity.user.name')->label('username'),
                    Tables\Columns\TextColumn::make('matters_count')->counts('matters')
                ]),
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
            'index' => Pages\ListCourts::route('/'),
            'create' => Pages\CreateCourt::route('/create'),
            'edit' => Pages\EditCourt::route('/{record}/edit'),
        ];
    }
}
