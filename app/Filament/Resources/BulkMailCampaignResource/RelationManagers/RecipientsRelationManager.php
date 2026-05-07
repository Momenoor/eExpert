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
use Illuminate\Support\Facades\Storage;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getModelLabel(): ?string
    {
        return __('Bulk Mail Recipient');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('Bulk Mail Recipients');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label(__('bulk_mail.fields.email'))
                ->email()
                ->required(),
            TextInput::make('name')
                ->label(__('bulk_mail.fields.name')),
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
                    ->label(__('bulk_mail.fields.email'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('bulk_mail.fields.name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('bulk_mail.fields.status'))
                    ->badge(),
                TextColumn::make('sent_at')
                    ->label(__('bulk_mail.fields.sent_at'))
                    ->dateTime(),
                TextColumn::make('failed_at')
                    ->label(__('bulk_mail.fields.failed_at'))
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
                    ->options(fn($livewire) => [
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
                    ->visible(fn($record) => filled($record->sent_at))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Storage::delete($record->pdf_path);
                        $record->update([
                            'status' => BulkMailRecipientStatus::Pending,
                            'sent_at' => null,
                            'failed_at' => null,
                            'attempt_count' => 0,
                            'pdf_path' => null,
                        ]);
                        $record->campaign->decrement('sent_count');

                    }),
                Action::make('print')
                    ->label(__('bulk_mail.actions.print'))
                    ->icon('heroicon-o-printer')
                    ->url(fn($record) => route('bulk-mail.preview', [
                        'campaign' => $record->campaign_id,
                        'recipient' => $record->id,
                        'print' => 1,   // auto-triggers print dialog
                    ]))
                    ->visible(fn($record) => filled($record->sent_at))
                    ->openUrlInNewTab(),

                // Preview only
                Action::make('preview')
                    ->label(__('bulk_mail.actions.preview'))
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('bulk-mail.preview', [
                        'campaign' => $record->campaign_id,
                        'recipient' => $record->id,
                    ]))
                    ->visible(fn($record) => filled($record->sent_at))
                    ->openUrlInNewTab(),
                Action::make('downloadPdf')
                    ->label(__('bulk_mail.actions.download_pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn($record) => filled($record->pdf_path))
                    ->url(fn($record) => Storage::url($record->pdf_path))
                    ->openUrlInNewTab(),

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
                                    $record->campaign->decrement('failed_count');
                                }
                            });
                        }),
                    BulkAction::make('resend')
                        ->label(__('bulk_mail.actions.resend'))
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                Storage::delete($record->pdf_path);
                                $record->update([
                                    'status' => BulkMailRecipientStatus::Pending,
                                    'sent_at' => null,
                                    'failed_at' => null,
                                    'attempt_count' => 0,
                                    'pdf_path' => null,
                                ]);
                                $record->campaign->decrement('sent_count');
                            });
                        }),
                    BulkAction::make('downloadPdf')
                        ->label(__('bulk_mail.actions.download_pdf'))
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->pdf_path) {
                                    Storage::download($record->pdf_path);
                                }
                            });
                        })
                ]),
            ]);
    }
}
