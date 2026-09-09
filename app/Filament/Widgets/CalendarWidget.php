<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Models\CalendarEvent;
use App\Models\Matter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    use HasWidgetShield;

    public Model|string|null $model = CalendarEvent::class;

    // Matches UpcomingSessionsWidget's span so the two sit side by side.
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'xl' => 3,
    ];

    /**
     * @throws ConnectionException
     */
    public function fetchEvents(array $info): array
    {
        return $date = CalendarEvent::query()
            ->with('matters') // Eager load the relationship
            ->where('start_datetime', '>=', $info['start'])
            ->where('end_datetime', '<=', $info['end'])
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'start' => $event->start_datetime,
                'end' => $event->end_datetime,
                'allDay' => $event->is_all_day,
                'extendedProps' => [
                    'location' => $event->location,
                    'description' => $event->description,
                    // Pass the matter numbers for the tooltip
                    'matters' => $event->matters->map(fn ($m) => "{$m->number}/{$m->year}")->implode(', '),
                ],
            ])
            ->toArray();
    }

    public function eventDidMount(): string
    {
        return 'function({ event, timeText, isStart, isEnd, isMirror, isPast, isFuture, isToday, el, view }){
            el.setAttribute("x-tooltip", "tooltip");
            el.setAttribute("x-data", "{ tooltip: \'"+event.title+"\' }");
        }';
    }

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'timeGridDay,timeGridWeek,dayGridMonth,listWeek',
                'center' => 'title',
                'right' => 'prev,next today',
            ],
            'initialView' => 'listWeek',
            // 'eventDisplay' => 'block',
            'scrollTime' => '09:00:00',
            'timeZone' => config('app.timezone'), // Or 'local'
            'slotMinTime' => '00:00:00', // Start workday at 8 AM
            'slotMaxTime' => '20:00:00',
        ];
    }

    public function getFormSchema(): array
    {
        return CalendarEventForm::getFormSchema();
    }

    protected function headerActions(): array
    {
        return [
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->modalHeading(__('Edit Calendar Event'))
                ->modalSubmitActionLabel(__('Save'))
                ->modalCancelActionLabel(__('Cancel'))
                ->visible(auth()->user()->can('Update:CalendarEvent')),
            Actions\DeleteAction::make()
                ->modalHeading(__('Delete Calendar Event'))
                ->modalSubmitActionLabel(__('Delete'))
                ->modalCancelActionLabel(__('Cancel'))
                ->requiresConfirmation()
                ->visible(auth()->user()->can('Delete:CalendarEvent')),
        ];
    }

    protected function viewAction(): Action
    {
        return Actions\ViewAction::make()
            ->modalHeading(__('Calendar Event Details'));
    }

    protected function getOptions(): array
    {
        return [
            'timeZone' => config('app.timezone'), // Or 'local'
            'firstDay' => 0, // Sunday (Common for Gulf region)
            'slotMinTime' => '08:00:00', // Start workday at 8 AM
            'slotMaxTime' => '20:00:00', // End at 8 PM
        ];
    }
}
