<?php

namespace App\Filament\Widgets;

use App\Models\PartyLeave;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class VacationCalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = PartyLeave::class;

    protected int|string|array $columnSpan = 1;

    public function fetchEvents(array $info): array
    {
        return PartyLeave::query()
            ->with('party')
            ->where('start_date', '<=', $info['end'])
            ->where('end_date', '>=', $info['start'])
            ->get()
            ->map(fn (PartyLeave $leave) => [
                'id' => $leave->id,
                'title' => $leave->party?->name ?? '—',
                'start' => $leave->start_date->toDateString(),
                // FullCalendar's end date is exclusive for all-day events.
                'end' => $leave->end_date->copy()->addDay()->toDateString(),
                'allDay' => true,
                'extendedProps' => [
                    'reason' => $leave->reason,
                ],
            ])
            ->toArray();
    }

    public function eventDidMount(): string
    {
        return 'function({ event, el }){
            if (event.extendedProps.reason) {
                el.setAttribute("x-tooltip", "tooltip");
                el.setAttribute("x-data", "{ tooltip: \'"+event.extendedProps.reason+"\' }");
            }
        }';
    }

    public function getHeading(): ?string
    {
        return __('Leave / Vacation Calendar');
    }

    public function config(): array
    {
        return [
            'firstDay' => 0, // Sunday (Common for Gulf region)
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,listMonth',
            ],
            'initialView' => 'dayGridMonth',
            'timeZone' => 'Asia/Muscat',
        ];
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }
}
