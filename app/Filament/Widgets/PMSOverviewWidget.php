<?php

namespace App\Filament\Widgets;

use App\Enums\PMS\InstallmentPaymentStatus;
use App\Enums\PMS\UnitStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Unit;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * A snapshot of the property portfolio: how much is vacant right now, how
 * much rent is currently overdue, and how many contracts are heading toward
 * expiry within the 90-day window Law No. 33 of 2008 requires notice inside.
 */
class PMSOverviewWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return 4;
    }

    protected function getStats(): array
    {
        $totalUnits = Unit::count();
        $vacantUnits = Unit::where('status', UnitStatus::VACANT)->count();

        $overdueInstallments = Installment::where('payment_status', InstallmentPaymentStatus::OVERDUE);
        $overdueCount = $overdueInstallments->count();
        $overdueTotal = (float) (clone $overdueInstallments)->sum('balance_due');

        $renewalsDueSoon = Contract::query()
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(90)->toDateString()])
            ->count();

        return [
            Stat::make(__('Vacant Units'), "{$vacantUnits} / {$totalUnits}")
                ->description(__('Currently vacant out of total units'))
                ->descriptionIcon('heroicon-m-home')
                ->color($vacantUnits > 0 ? 'warning' : Color::Green),
            Stat::make(__('Overdue Instalments'), $overdueCount)
                ->description(__(':amount AED outstanding', ['amount' => number_format($overdueTotal, 2)]))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : Color::Green),
            Stat::make(__('Renewals Due (90 Days)'), $renewalsDueSoon)
                ->description(__('Contracts expiring within the notice window'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($renewalsDueSoon > 0 ? 'warning' : Color::Green)
                ->url(ContractResource::getUrl('index')),
        ];
    }
}
