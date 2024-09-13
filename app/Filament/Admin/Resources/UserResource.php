<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->columns(2)->schema([
                    Forms\Components\FileUpload::make('avatar')
                        ->disk('public')
                        ->directory('avatars')
                        ->visible('public')
                        ->image()
                        ->avatar()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignorable: fn($record) => $record)
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('email')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignorable: fn($record) => $record)
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('display_name')
                        ->grow(true)
                        ->columnSpan(1),
                ]),

                Forms\Components\Section::make()->columns(2)->schema([
                    Forms\Components\Radio::make('gender')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),
                    Forms\Components\Radio::make('language')
                        ->options([
                            'ar' => 'Arabic',
                            'en' => 'English',
                        ]),
                    Forms\Components\Select::make('roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                ]),
            ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('language')
                    ->badge(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('language')
                    ->options(['ar' => 'Arabic', 'en' => 'English']),
                Tables\Filters\SelectFilter::make('gender')
                    ->options(['male' => 'Male', 'female' => 'Female']),
                Tables\Filters\SelectFilter::make('roles')
                ->relationship('roles', 'name'),

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
            'index' => \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Admin\Resources\UserResource\Pages\CreateUser::route('/create'),
            'view' => \App\Filament\Admin\Resources\UserResource\Pages\ViewUser::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
