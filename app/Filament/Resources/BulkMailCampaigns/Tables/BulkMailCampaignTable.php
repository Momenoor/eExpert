<?php

namespace App\Filament\Resources\BulkMailCampaigns\Tables;

use App\Filament\Imports\BulkMailRecipientImporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BulkMailCampaignTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('bulk_mail.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('from_sender_key')
                    ->label(__('bulk_mail.fields.from_sender'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('bulk_mail.fields.status'))
                    ->badge(),
                TextColumn::make('stats')
                    ->label(__('bulk_mail.fields.progress'))
                    ->getStateUsing(fn($record) => "{$record->sent_count} / {$record->total_recipients} (" . __('bulk_mail.fields.failed') . ": {$record->failed_count})"),
                TextColumn::make('scheduled_at')
                    ->label(__('bulk_mail.fields.scheduled_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('bulk_mail.fields.created_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ImportAction::make()
                    ->importer(BulkMailRecipientImporter::class)
                    ->label(__('bulk_mail.actions.import'))
                    ->options(fn($record) => [
                        'campaign_id' => $record->id,
                    ])
                    ->icon('heroicon-o-arrow-up-tray'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
