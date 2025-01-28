<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpertResource\Pages;
use App\Filament\Admin\Resources\ExpertResource\RelationManagers;
use App\Filament\Forms\ExpertForm;
use App\Models\Expert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ExpertResource extends Resource
{
    protected static ?string $model = Expert::class;
    protected static ?string $navigationGroup = 'Experts';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(ExpertForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(components: [
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('entity.name')
                        ->label('name')
                        ->searchable(),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('entity.email')->icon('heroicon-o-envelope')
                            ->searchable()
                            ->label('email'),
                        Tables\Columns\TextColumn::make('entity.mobile')->icon('heroicon-o-phone')
                            ->searchable()
                            ->label('mobile'),
                    ])->space(),
                    Tables\Columns\TextColumn::make('types.name')
                        ->label('type')
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->searchable(),
                    Tables\Columns\TextColumn::make('expertiseField.name')
                        ->label('field')
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->searchable(),
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
            'index' => Pages\ListExperts::route('/'),
            'create' => Pages\CreateExpert::route('/create'),
            'edit' => Pages\EditExpert::route('/{record}/edit'),
        ];
    }
}
