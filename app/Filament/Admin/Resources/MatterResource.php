<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MatterResource\Pages;
use App\Filament\Admin\Resources\MatterResource\RelationManagers;
use App\Filament\Forms\MatterForm;
use App\Models\Matter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;

class MatterResource extends Resource
{
    protected static ?string $model = Matter::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(MatterForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListMatters::route('/'),
            'create' => Pages\CreateMatter::route('/create'),
            'edit' => Pages\EditMatter::route('/{record}/edit'),
        ];
    }
}
