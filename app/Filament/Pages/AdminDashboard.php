<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttentionNeededWidget;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\CollectionsAgingWidget;
use App\Filament\Widgets\MattersPerYearWidget;
use App\Filament\Widgets\MatterStatsWidget;
use App\Filament\Widgets\UpcomingSessionsWidget;
use App\Filament\Widgets\VacationCalendarWidget;
use Filament\Pages\Dashboard;
use JibayMcs\FilamentTour\Tour\HasTour;
use JibayMcs\FilamentTour\Tour\Step;
use JibayMcs\FilamentTour\Tour\Tour;

class AdminDashboard extends Dashboard
{
    use HasTour;

    /**
     * 6 across on a wide screen — the smallest count that lets Upcoming
     * Sessions pair evenly with Calendar (3+3) on the same row as Collections
     * Aging, Matters Per Year and Vacation Calendar split evenly three ways
     * (2+2+2) on the row after it. 'full'-span widgets (Attention Needed,
     * Matter Stats) always take their own row regardless of this count.
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 6,
        ];
    }

    public function getWidgets(): array
    {
        // Ordered as: what needs attention now, then the numbers, then
        // Sessions paired with the Calendar, then the three smaller widgets
        // together — order matters here as much as columnSpan, since
        // Filament lays widgets out in this sequence and wraps by width. Each
        // widget declares its own canView(), so a user only ever sees the
        // ones their permissions allow.
        return [
            AttentionNeededWidget::class,
            MatterStatsWidget::class,
            UpcomingSessionsWidget::class,
            CalendarWidget::class,
            CollectionsAgingWidget::class,
            MattersPerYearWidget::class,
            VacationCalendarWidget::class,
        ];
    }

    /**
     * @throws \Exception
     */
    public function tours(): array
    {
        return [
            Tour::make('font-size-feature')
                ->ignoreRoutes()
                ->steps(
                    Step::make('.fi-avatar') // targets the user avatar in the top bar
                        ->title(__('Personalize Your Experience'))
                        ->description(__('Click your avatar to open the user menu.'))
                        ->icon('heroicon-o-user-circle')
                        ->iconColor('primary'),

                    Step::make('#font-size-slider') // targets the slider wrapper
                        ->title(__('Font Size Control'))
                        ->description(__('Use this slider to increase or decrease the font size across the entire panel. Your preference is saved automatically.'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->iconColor('primary'),
                ),
        ];
    }
}
