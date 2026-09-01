<?php

namespace App\Filament\Resources\MatterRequests\Schemas;

use App\Enums\RequestStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Storage;

class MatterRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        // Left Column: Primary Request Details
                        Group::make()
                            ->schema([
                                Section::make(__('Request Information'))
                                    ->description(__('Core details regarding this submission.'))
                                    ->icon('heroicon-m-information-circle')
                                    ->schema([
                                        TextEntry::make('matter')
                                            ->label(__('Matter Reference'))
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->icon('heroicon-m-hashtag')
                                            ->url(fn ($record) => route('filament.admin.resources.matters.view', $record->matter_id))
                                            ->formatStateUsing(fn ($state) => "{$state->number}/{$state->year}"),
                                        TextEntry::make('requestBy.display_name')
                                            ->label(__('Request By')),
                                        Grid::make(2)->schema([
                                            TextEntry::make('type')
                                                ->label(__('Request Type'))
                                                ->badge()
                                                ->color('gray')
                                                ->icon('heroicon-m-tag'),
                                            TextEntry::make('status')
                                                ->label(__('Status'))
                                                ->badge(),
                                        ]),

                                        TextEntry::make('comment')
                                            ->label(__('Request Description'))
                                            ->markdown()
                                            ->prose()
                                            ->columnSpanFull()
                                            ->placeholder(__('No comments provided.')),
                                    ])->columns(1),
                            ])->columnSpan(2),

                        // Right Column: Metadata & Audit Trail
                        Group::make()
                            ->schema([
                                Section::make(__('Approval Details'))
                                    ->icon('heroicon-m-check-badge')
                                    ->collapsed(fn ($record) => $record->status !== RequestStatus::APPROVED) // Auto-expand if approved
                                    ->schema([
                                        TextEntry::make('approvedBy.name')
                                            ->label(__('Handled By'))
                                            ->icon('heroicon-m-user')
                                            ->placeholder('-'),
                                        TextEntry::make('approved_at')
                                            ->label(__('Handled At'))
                                            ->since()
                                            ->dateTime('M d, Y H:i')
                                            ->size(TextSize::Small)
                                            ->color('gray'),
                                        TextEntry::make('approved_comment')
                                            ->label(__('Handling Note'))
                                            ->color('gray')
                                            ->placeholder('-'),
                                    ]),

                                Section::make(__('Timestamps'))
                                    ->icon('heroicon-m-clock')
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label(__('Created At'))
                                            ->since()
                                            ->dateTimeTooltip(),
                                        TextEntry::make('updated_at')
                                            ->label(__('Last Activity'))
                                            ->since()
                                            ->dateTimeTooltip(),
                                    ]),
                            ])->columnSpan(1),
                        Section::make(__('Attachments'))
                            ->columnSpanFull()
                            ->icon('heroicon-m-paper-clip')
                            ->schema([
                                RepeatableEntry::make('attachments')
                                    ->hiddenLabel()
                                    ->columns(5)
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => $record->attachments->isNotEmpty())
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('Name'))
                                            ->color('primary')
                                            ->icon('heroicon-o-document-text')
                                            ->url(fn ($record) => Storage::disk('public')->url($record->path))
                                            ->openUrlInNewTab()
                                            ->alignJustify()
                                            ->columnSpan(2),
                                        TextEntry::make('size')
                                            ->label(__('Size'))
                                            ->numeric()
                                            ->formatStateUsing(fn ($state) => number_format($state / (1024 * 1024), 2))
                                            ->suffix('MB'),
                                        TextEntry::make('created_at')
                                            ->label(__('Created At'))
                                            ->since()
                                            ->dateTimeTooltip(),
                                        TextEntry::make('updated_at')
                                            ->label(__('Last Activity'))
                                            ->since()
                                            ->dateTimeTooltip(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
