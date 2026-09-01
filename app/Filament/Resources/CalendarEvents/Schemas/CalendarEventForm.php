<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Models\Matter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CalendarEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function buildDescription(Matter $matter): string
    {
        $plaintiffs = $matter->mainPartiesOnly
            ->where('type', 'plaintiff')
            ->map(fn ($mp) => $mp->party?->name)->filter()->join(', ');

        $defendants = $matter->mainPartiesOnly
            ->where('type', 'defendant')
            ->map(fn ($mp) => $mp->party?->name)->filter()->join(', ');

        $experts = $matter->expertsOnly
            ->map(fn ($mp) => $mp->party?->name)->filter()->join(', ');

        return collect([
            __('Matter').': '.$matter->year.'/'.$matter->number,
            __('Court').': '.($matter->court?->name ?? '—'),
            __('Type').': '.($matter->type?->name ?? '—'),
            $plaintiffs ? __('Plaintiffs').': '.$plaintiffs : null,
            $defendants ? __('Defendants').': '.$defendants : null,
            $experts ? __('Experts').': '.$experts : null,
        ])->filter()->join("\n");
    }

    public static function getFormSchema(?int $matterId = null): array
    {
        return [

            Section::make(__('Event Details'))->schema([
                Select::make('matter_id')
                    ->label(__('Matter'))
                    ->relationship('matter', 'year')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->year.'/'.$record?->number.' - '.($record?->court?->name ?? '').' - '.($record?->type?->name ?? ''))
                    ->placeholder(__('Select Matter'))
                    ->searchable()
                    ->preload()
                    ->hidden((fn ($record) => $record instanceof Matter))
                    ->disabled(fn ($record) => $record instanceof Matter)
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $matter = Matter::with(['court', 'type', 'mainPartiesOnly.party', 'expertsOnly.party'])
                            ->find($state);
                        if (! $matter) {
                            return;
                        }

                        $set('title', $matter->year.'/'.$matter->number
                            .' — '.($matter->court?->name ?? '')
                            .' — '.($matter->type?->name ?? ''));
                        $set('location', 'Microsoft Teams - '.$matter->court?->name ?? 'Microsoft Teams');

                        //                        if ($matter->next_session_date) {
                        //                            $set('start_at', Carbon::parse($matter->next_session_date)->format('Y-m-d H:i:s'));
                        //                            $set('end_at', Carbon::parse($matter->next_session_date)->addHour()->format('Y-m-d H:i:s'));
                        //                        }

                        $set('description', self::buildDescription($matter));
                    })
                    ->columnSpanFull(),

                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->columnSpanFull(),

                DateTimePicker::make('start_datetime')
                    ->label(__('Start At'))
                    ->seconds(false)
                    ->required()
                    ->timezone(config('app.timezone'))
                    ->minutesStep(15)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('end_datetime', $state ? Carbon::parse($state)->addHour()->format('Y-m-d H:i:s') : null)
                    ),

                DateTimePicker::make('end_datetime')
                    ->label(__('End At'))
                    ->seconds(false)
                    ->minutesStep(15)
                    ->timezone(config('app.timezone'))
                    ->afterOrEqual('start_datetime')
                    ->required(),

                Toggle::make('is_all_day')
                    ->label(__('All Day'))
                    ->default(false)
                    ->columnSpanFull(),

                TextInput::make('location')
                    ->label(__('Location'))
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull(),

                Toggle::make('update_next_session_date')
                    ->label(__("Update matter's next session date"))
                    ->default(true)
                    ->disabled(fn (Get $get) => empty($get('matter_id'))),

                Toggle::make('sync_to_outlook')
                    ->label(__('Sync to Outlook Calendar'))
                    ->default(true),

            ])->columns(2),

            Section::make(__('Online Meeting'))->schema([
                Toggle::make('is_teams_meeting')
                    ->label(__('Create Teams Meeting'))
                    ->default(true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $location = $get('location');
                        if (! $state) {
                            $location = Str::of($location)->remove('Microsoft Teams - ');
                        } else {
                            $location = 'Microsoft Teams - '.$location;
                        }
                        $set('location', $location);
                    })
                    ->helperText(__('Creates a Microsoft Teams meeting link with this event')),

                TextInput::make('online_meeting_url')
                    ->label(__('Teams Meeting URL'))
                    ->url()
                    ->disabled()
                    ->dehydrated(false) // Ensures it doesn't try to save back to DB if disabled
                    ->placeholder(__('Generated after sync'))
                    ->suffixIcon('heroicon-o-video-camera')
                    ->visible(fn ($state) => ! empty($state))
                    ->suffixAction(
                        Action::make('openUrl')
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->tooltip(__('Join Meeting'))
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                    ),
            ]),

        ];
    }
}
