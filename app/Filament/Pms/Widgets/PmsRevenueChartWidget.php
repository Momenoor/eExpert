<?php

namespace App\Filament\Pms\Widgets;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Collected rent revenue (paid installments) by month, trailing 12 months —
 * the PMS dashboard's revenue counterpart to PMSOverviewWidget's occupancy
 * stats.
 */
class PmsRevenueChartWidget extends ChartWidget
{
    use HasWidgetShield;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('Revenue (Last 12 Months)');
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))
            ->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $paidByMonth = Installment::query()
            ->where('payment_status', InstallmentPaymentStatus::PAID)
            ->whereNotNull('paid_date')
            ->where('paid_date', '>=', $months->first())
            ->get()
            ->groupBy(fn (Installment $installment) => $installment->getAttribute('paid_date')->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('paid_amount'));

        return [
            'datasets' => [
                [
                    'label' => __('Collected Revenue (AED)'),
                    'data' => $months->map(fn ($month) => $paidByMonth->get($month->format('Y-m'), 0.0))->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => $months->map(fn ($month) => $month->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
