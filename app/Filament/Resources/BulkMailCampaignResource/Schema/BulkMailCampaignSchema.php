<?php

namespace App\Filament\Resources\BulkMailCampaignResource\Schema;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\HtmlString;

class BulkMailCampaignSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('bulk_mail.sections.details'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('bulk_mail.fields.name'))
                        ->required(),
                    TextInput::make('subject')
                        ->label(__('bulk_mail.fields.subject'))
                        ->required()
                        ->hint(__('bulk_mail.hints.subject')),
                    RichEditor::make('body')
                        ->label(__('bulk_mail.fields.body'))
                        ->required()
                        ->hint(__('bulk_mail.hints.body'))

                        ->columnSpanFull()
                        ->live(),

                    Select::make('from_sender_key')
                        ->label(__('bulk_mail.fields.from_sender'))
                        ->options(collect(Config::get('mail_senders.senders'))->mapWithKeys(fn($s, $k) => [$k => $s['name']]))
                        ->required()
                        ->live(),

                    TextEntry::make('sender_signature')
                        ->label(__('Signature Preview'))
                        ->state(fn ($get) => new HtmlString(Config::get("mail_senders.senders.{$get('from_sender_key')}.signature", '')))
                        ->visible(fn ($get) => filled($get('from_sender_key'))),
                ])->columns(2),

            Section::make(__('bulk_mail.sections.recipients'))
                ->schema([
                    TagsInput::make('cc_emails')
                        ->label(__('bulk_mail.fields.cc_emails'))
                        ->placeholder('email@example.com'),
                    TagsInput::make('bcc_emails')
                        ->label(__('bulk_mail.fields.bcc_emails'))
                        ->placeholder('email@example.com'),
                ])->columns(2),

            Section::make(__('bulk_mail.sections.placeholders'))
                ->schema([
                    TagsInput::make('placeholders')
                        ->label(__('bulk_mail.fields.placeholders'))
                        ->hint(__('bulk_mail.hints.placeholders')),
                ]),

            Section::make(__('bulk_mail.sections.attachment'))
                ->schema([
                    Toggle::make('has_attachment')
                        ->label(__('bulk_mail.fields.has_attachment'))
                        ->live(),
                    FileUpload::make('attachment_path')
                        ->label(__('bulk_mail.fields.attachment'))
                        ->disk('public')
                        ->multiple()
                        ->previewable()
                        ->downloadable()
                        ->directory('mail_attachments')
                        ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240)
                        ->visible(fn ($get) => $get('has_attachment')),
                ]),

            Section::make(__('bulk_mail.sections.schedule'))
                ->schema([
                    TextInput::make('daily_send_limit')
                        ->label(__('bulk_mail.fields.daily_limit'))
                        ->numeric()
                        ->default(50)
                        ->required(),
                    DateTimePicker::make('scheduled_at')
                        ->label(__('bulk_mail.fields.scheduled_at'))
                        ->hint(__('bulk_mail.hints.scheduled_at')),
                    Select::make('status')
                        ->label(__('bulk_mail.fields.status'))
                        ->options(\App\Enums\BulkMailCampaignStatus::class)
                        ->required()
                        ->default(\App\Enums\BulkMailCampaignStatus::Draft),
                ])->columns(3),
        ]);
    }
}
