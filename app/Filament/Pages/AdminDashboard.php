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

    public function getWidgets(): array
    {
        // Ordered as: what needs attention now, then the numbers, then the
        // calendars. Each widget declares its own canView(), so a user only ever
        // sees the ones their permissions allow.
        return [
            AttentionNeededWidget::class,
            MatterStatsWidget::class,
            UpcomingSessionsWidget::class,
            CollectionsAgingWidget::class,
            MattersPerYearWidget::class,
            CalendarWidget::class,
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
