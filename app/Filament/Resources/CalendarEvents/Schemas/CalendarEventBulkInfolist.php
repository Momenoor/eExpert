<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Helpers\HtmlFormatter;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class CalendarEventBulkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make(__('Event Details'))->schema([
                TextEntry::make('title')
                    ->label(__('Title'))
                    ->weight(FontWeight::Bold)
                    ->columnSpanFull(),

                TextEntry::make('matters')
                    ->label(__('Matters'))
                    ->formatStateUsing(fn ($state) => $state->number.'/'.$state->year)
                    ->bulleted(),

                TextEntry::make('start_datetime')
                    ->label(__('Start At'))
                    ->dateTime('d M Y H:i'),

                TextEntry::make('end_datetime')
                    ->label(__('End At'))
                    ->dateTime('d M Y H:i'),

                IconEntry::make('is_all_day')
                    ->label(__('All Day'))
                    ->boolean(),

                TextEntry::make('location')
                    ->label(__('Location'))
                    ->placeholder('—')
                    ->icon('heroicon-o-map-pin'),

                TextEntry::make('description')
                    ->label(__('Description'))
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->html()
                    ->formatStateUsing(fn (?string $state) => HtmlFormatter::linkify($state)),

            ])->columns(2),

            Section::make(__('Sync Status'))->schema([
                TextEntry::make('outlook_event_id')
                    ->label(__('Outlook Event'))
                    ->placeholder(__('Not synced'))
                    ->formatStateUsing(fn ($state) => $state ? __('Synced') : __('Not synced'))
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextEntry::make('teams_meeting_url')
                    ->label(__('Teams Meeting'))
                    ->placeholder(__('No meeting link'))
                    ->url(fn ($state) => $state)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-video-camera')
                    ->iconColor('info')
                    ->visible(fn ($record) => filled($record->teams_meeting_url)),

            ])->columns(2),

        ]);
    }
}
