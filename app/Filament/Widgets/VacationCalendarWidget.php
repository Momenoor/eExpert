<?php

namespace App\Filament\Widgets;

use App\Models\PartyLeave;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class VacationCalendarWidget extends FullCalendarWidget
{
    /**
     * Who is on leave is staff data.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PartyLeave') ?? false;
    }

    public Model|string|null $model = PartyLeave::class;

    // A third of the dashboard's row at 'xl' (2 of 6 columns), so this
    // sits alongside the other two small widgets on one row instead of
    // pairing up two at a time.
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'xl' => 2,
    ];

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
            'timeZone' => config('app.timezone'),
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
