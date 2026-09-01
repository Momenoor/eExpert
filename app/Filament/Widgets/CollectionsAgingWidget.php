<?php

namespace App\Filament\Widgets;

use App\Enums\FeeType;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding fees bucketed by how long they have been owed.
 *
 * Nothing in the system computed aging before this — collection_status only
 * says paid / partial / unpaid, never "how overdue". Buckets run from the fee's
 * REGISTRATION date (fees.date), which is the date the office became owed the
 * money, not when any part of it happened to be collected.
 *
 * VAT and deduction-type fees are excluded: VAT is a pass-through tax and the
 * deduction types reduce revenue rather than representing money owed to us.
 */
class CollectionsAgingWidget extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('Collections Aging');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->can('CollectFee:Matter')
            || $user?->can('ViewAny:IncentiveCalculation')
            || false;
    }

    protected function getData(): array
    {
        $buckets = [
            '0-30' => [0, 30],
            '31-60' => [31, 60],
            '61-90' => [61, 90],
            '90+' => [91, null],
        ];

        $outstanding = [];

        foreach ($buckets as $range) {
            [$from, $to] = $range;

            // Collected is joined in as a pre-grouped subquery, NOT a correlated
            // subquery inside the SUM(): a correlation referencing fees.id from
            // within an aggregate resolves against an arbitrary row, which made
            // an earlier version of this widget report the gross total as
            // outstanding instead of the actual balance.
            $collected = DB::table('allocations')
                ->selectRaw('fee_id, SUM(amount) as collected')
                ->groupBy('fee_id');

            $query = DB::table('fees')
                ->join('matters', 'matters.id', '=', 'fees.matter_id')
                ->leftJoinSub($collected, 'paid', 'paid.fee_id', '=', 'fees.id')
                ->whereNull('matters.deleted_at')
                ->whereNotNull('fees.date')
                ->where(function ($q) {
                    $q->whereNull('fees.type')
                        ->orWhereNotIn('fees.type', FeeType::excludedFromIncentiveValues());
                })
                ->whereDate('fees.date', '<=', now()->subDays($from)->toDateString());

            if ($to !== null) {
                $query->whereDate('fees.date', '>=', now()->subDays($to)->toDateString());
            }

            $total = (float) $query
                ->selectRaw('COALESCE(SUM(fees.amount) - SUM(COALESCE(paid.collected, 0)), 0) as outstanding')
                ->value('outstanding');

            // Clamped at zero: a bucket can be net over-collected (production
            // currently has ~400 fees whose allocations exceed the fee, because
            // the gross payment is allocated to the expert fee while the office
            // share is recorded separately). A negative bar would read as
            // nonsense here, so the bucket floors at nothing outstanding.
            $outstanding[] = round(max(0, $total), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Outstanding (AED)'),
                    'data' => $outstanding,
                    'backgroundColor' => [
                        'rgba(16, 185, 129, 0.65)',
                        'rgba(245, 158, 11, 0.65)',
                        'rgba(249, 115, 22, 0.65)',
                        'rgba(239, 68, 68, 0.65)',
                    ],
                ],
            ],
            'labels' => [
                __('0-30 days'),
                __('31-60 days'),
                __('61-90 days'),
                __('90+ days'),
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
