<?php

namespace App\Filament\Pms\Pages;

use App\Filament\Pms\Widgets\PMSOverviewWidget;
use App\Filament\Pms\Widgets\PmsRevenueChartWidget;
use Filament\Pages\Dashboard;

class PmsDashboard extends Dashboard
{
    public function getWidgets(): array
    {
        return [
            PMSOverviewWidget::class,
            PmsRevenueChartWidget::class,
        ];
    }
}
