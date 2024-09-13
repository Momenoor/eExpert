<?php

namespace App\Filament\Admin\Resources;

use App\Enum\EntityRoleEnum;
use App\Filament\Admin\Resources\ExpertResource\Pages;
use App\Filament\Admin\Resources\ExpertResource\RelationManagers;
use App\Filament\Forms\Components\StatusToggle;
use App\Models\Expert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ExpertResource extends Resource
{
    protected static ?string $model = Expert::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignorable: fn($record) => $record)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
                Forms\Components\TextArea::make('address'),
                Forms\Components\TextInput::make('fax')
                    ->tel(),
                Forms\Components\Select::make('roles.expertField')
                    ->label('Expert Field')
                    ->relationship('roles.expertField', 'name')
                    ->placeholder('Select Expert Field'),
                Forms\Components\Select::make('roles.name')
                    ->relationship('roles', 'name')
                    ->options(EntityRoleEnum::Expert->subRoles())
                    ->required(),
                StatusToggle::make(),
                Forms\Components\Toggle::make('user_required')
                    ->label('Is user required?')
                    ->default(false) // Default to false (not adding a user)
                    ->hint('Toggle to decide whether to add a user or not.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-m-envelope'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('expertField.name'),
                Tables\Columns\TextColumn::make('roles.name'),

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
            'index' => Pages\ListExperts::route('/'),
            'create' => Pages\CreateExpert::route('/create'),
            'view' => Pages\ViewExpert::route('/{record}'),
            'edit' => Pages\EditExpert::route('/{record}/edit'),
        ];
    }
}
