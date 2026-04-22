<?php

namespace App\Filament\Resources\Types\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FieldDefinitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldDefinitions';

    public static function getModelLabel(): string
    {
        return __('Field Definition');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Field Definitions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label(__('Label'))
                    ->required(),
                Select::make('type')
                    ->label(__('Type'))
                    ->options([
                        'text_input' => __('text_input'),
                        'select_input' => __('select_input'),
                        'date_input' => __('date_input'),
                        "toggle_input" => __("toggle_input")
                    ])
                    ->required()
                    ->live(),
                Toggle::make('required')
                    ->label(__('Required')),
                KeyValue::make('options')
                    ->label(__('Options'))
                    ->visible(fn(Get $get) => $get('type') === 'select'),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('label')
                    ->label(__('Label')),
                TextEntry::make('type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn($state) => __($state)),
                IconEntry::make('required')
                    ->label(__('Required'))
                    ->boolean(),
                KeyValueEntry::make('options')
                    ->label(__('Options'))
                    ->visible(fn($record) => $record->type === 'select'),
                TextEntry::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn($state) => __($state))
                    ->searchable(),
                IconColumn::make('required')
                    ->label(__('Required'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
