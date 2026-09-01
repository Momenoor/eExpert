<?php

namespace App\Filament\Actions\Calendar;

use App\Models\CalendarEvent;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Type;
use App\Services\OutlookCalendarService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateBulkCalendarEventAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createBulkEvent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Create Bulk Event'))
            ->modalHeading(__('Create Bulk Calendar Event'))
            ->icon('heroicon-o-rectangle-stack')
            ->form([
                Select::make('matter_type_id')
                    ->label(__('Matter Type'))
                    ->options(Type::pluck('name', 'id'))
                    ->required()
                    ->live(),
                Select::make('court_id')
                    ->label(__('Court'))
                    ->options(Court::pluck('name', 'id'))
                    ->required()
                    ->live(),
                CheckboxList::make('matter_ids')
                    ->label(__('Matters'))
                    ->options(function (callable $get) {
                        $typeId = $get('matter_type_id');
                        if (! $typeId) {
                            return [];
                        }

                        return Matter::where('type_id', $typeId)
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => "{$m->number}/{$m->year}"])
                            ->toArray();
                    })
                    ->required()
                    ->columns(2)
                    ->live(),
                // config('app.timezone'), not a hardcoded zone: this
                // picker previously said 'Asia/Dubai' while the single-event
                // form said 'Asia/Muscat'. Both share the same fixed UTC+4
                // offset today, so the mismatch produced no visible bug, but
                // it meant the app's notion of "local time" was defined in
                // two places that could drift.
                DateTimePicker::make('start_datetime')
                    ->label(__('Session Date & Time'))
                    ->required()
                    ->timezone(config('app.timezone')),
                DateTimePicker::make('end_datetime')
                    ->label(__('End Date & Time'))
                    ->timezone(config('app.timezone')),
                TextInput::make('location')
                    ->label(__('Location')),
                Placeholder::make('generated_title')
                    ->label(__('Generated Title'))
                    ->content(function (callable $get) {
                        $matterIds = $get('matter_ids');
                        $courtId = $get('court_id');
                        $typeId = $get('matter_type_id');

                        if (empty($matterIds) || ! $courtId || ! $typeId) {
                            return '—';
                        }

                        $matters = Matter::whereIn('id', $matterIds)->get();
                        $matterNumbers = $matters->map(fn ($m) => "{$m->number}/{$m->year}")->join(', ');
                        $courtName = Court::find($courtId)?->name;
                        $typeName = Type::find($typeId)?->name;

                        return "{$matterNumbers} ({$courtName}) ({$typeName}) (جلسات المحكمة)";
                    }),
                Toggle::make('sync_to_outlook')
                    ->label(__('Sync to Outlook Calendar'))
                    ->default(true)
                    ->live(),
                Toggle::make('is_teams_meeting')
                    ->label(__('Create Teams Meeting'))
                    ->default(false)
                    ->visible(fn (callable $get) => $get('sync_to_outlook')),
            ])
            ->action(function (array $data, OutlookCalendarService $outlookService) {
                $matters = Matter::whereIn('id', $data['matter_ids'])->get();
                $matterNumbers = $matters->map(fn ($m) => "{$m->number}/{$m->year}")->join(', ');
                $courtName = Court::find($data['court_id'])?->name;
                $typeName = Type::find($data['matter_type_id'])?->name;

                $title = "{$matterNumbers} ({$courtName}) ({$typeName}) (جلسات المحكمة)";

                $event = CalendarEvent::create([
                    'title' => $title,
                    'start_datetime' => $data['start_datetime'],
                    'end_datetime' => $data['end_datetime'],
                    'location' => $data['location'],
                    'type' => 'bulk',
                    'created_by' => Auth::id(),
                ]);

                $event->matters()->attach($data['matter_ids']);

                // Update next_session_date on ALL selected matters
                Matter::whereIn('id', $data['matter_ids'])->update(['next_session_date' => $data['start_datetime']]);

                if ($data['sync_to_outlook']) {
                    try {
                        $userEmail = config('services.outlook.user_email');
                        $outlookEvent = $outlookService->createEvent([
                            'title' => $title,
                            'start_datetime' => $data['start_datetime'],
                            'end_datetime' => $data['end_datetime'],
                            'location' => $data['location'],
                            'is_teams_meeting' => $data['is_teams_meeting'] ?? false,
                        ], $userEmail);

                        $event->update([
                            'outlook_event_id' => $outlookEvent['id'],
                            'synced_to_outlook' => true,
                            'online_meeting_url' => $outlookEvent['onlineMeeting']['joinUrl'] ?? $outlookEvent['webLink'] ?? null,
                        ]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Outlook Sync Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }

                Notification::make()
                    ->title(__('Bulk Events Created Successfully'))
                    ->success()
                    ->send();
            });
    }
}
