<?php

namespace App\Filament\Widgets;

use App\Models\Matter;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class MattersPerYearWidget extends ChartWidget
{
    /**
     * Matter volume is operational data.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Matter') ?? false;
    }

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('Matters Received Per Year');
    }

    protected function getData(): array
    {
        $data = Trend::model(Matter::class)
            ->dateColumn('received_at')
            ->between(
                start: now()->subYears(9)->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perYear()
            ->count();

        //            Matter::query()
        //            ->selectRaw('YEAR(distributed_at) as year, count(*) as total')
        //            ->groupBy('year')
        //            ->orderBy('year', 'desc')
        //            ->get();
        return [
            'datasets' => [
                [
                    'label' => __('Matters Per Year'),
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(201, 203, 207, 0.2)',
                    ],
                    'borderColor' => [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 159, 64)',
                        'rgb(75, 192, 192)',
                        'rgb(255, 205, 86)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),

        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
