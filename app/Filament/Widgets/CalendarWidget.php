<?php

namespace App\Filament\Widgets;

use App\Models\CalendarEvent;
use App\Models\Matter;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    /**
     * Calendar entries can name matters and parties.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:CalendarEvent') ?? false;
    }

    public Model|string|null $model = CalendarEvent::class;

    protected int|string|array $columnSpan = 1;

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
                'start' => $event->start_datetime->subHours(4),
                'end' => $event->end_datetime->subHours(4),
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

    protected function headerActions(): array
    {
        return [
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->mountUsing(
                    fn (Schema $form, array $arguments) => $form->fill([
                        'title' => $arguments['event']['title'] ?? null,
                        'start_at' => $arguments['event']['start'] ?? null,
                        'end_at' => $arguments['event']['end'] ?? null,
                        'location' => $arguments['event']['extendedProps']['location'] ?? null,
                        'description' => $arguments['event']['extendedProps']['description'] ?? null,
                    ])
                ),
            Actions\DeleteAction::make(),
        ];
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
