<?php

namespace App\Filament\Resources\BulkMailCampaignResource\RelationManagers;

use App\Enums\BulkMailRecipientStatus;
use App\Filament\Imports\BulkMailRecipientImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('name'),
                TagsInput::make('cc_emails')
                    ->label(__('bulk_mail.fields.cc_emails')),
                KeyValue::make('placeholders')
                    ->label(__('bulk_mail.fields.placeholders')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('sent_at')
                    ->dateTime(),
                TextColumn::make('failed_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->increment('total_recipients');
                    }),
                ImportAction::make()
                    ->importer(BulkMailRecipientImporter::class)
                    ->options(fn ($livewire) => [
                        'campaign_id' => $livewire->getOwnerRecord()->id,
                    ])
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->loadCount('recipients');
                        $livewire->getOwnerRecord()->update([
                            'total_recipients' => $livewire->getOwnerRecord()->recipients_count,
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(function ($livewire) {
                        $livewire->getOwnerRecord()->decrement('total_recipients');
                    }),
                Action::make('resend')
                    ->label(__('bulk_mail.actions.resend'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => BulkMailRecipientStatus::Pending,
                            'sent_at' => null,
                            'failed_at' => null,
                            'attempt_count' => 0,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('retry_failed')
                        ->label(__('bulk_mail.actions.retry_failed'))
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->status === BulkMailRecipientStatus::Failed) {
                                    $record->update([
                                        'status' => BulkMailRecipientStatus::Pending,
                                        'sent_at' => null,
                                        'failed_at' => null,
                                        'attempt_count' => 0,
                                    ]);
                                }
                            });
                        }),
                ]),
            ]);
    }
}
