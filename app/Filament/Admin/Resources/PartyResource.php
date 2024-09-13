<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Forms\Components\StatusToggle;
use App\Filament\Resources\PartyResource\Pages;
use App\Filament\Resources\PartyResource\RelationManagers;
use App\Models\Party;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignorable: fn($record) => $record),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
                Forms\Components\TextInput::make('fax')
                    ->tel(),
                Forms\Components\Textarea::make('address'),

                StatusToggle::make(),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

            ])
            ->filters([
                //
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
            'index' => \App\Filament\Admin\Resources\PartyResource\Pages\ListParties::route('/'),
            'create' => \App\Filament\Admin\Resources\PartyResource\Pages\CreateParty::route('/create'),
            'view' => \App\Filament\Admin\Resources\PartyResource\Pages\ViewParty::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\PartyResource\Pages\EditParty::route('/{record}/edit'),
        ];
    }
}
