<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding balances bucketed by how long they have been owed.
 *
 * Nothing in the system computed aging before this — collection_status only
 * says paid / partial / unpaid, never "how overdue".
 *
 * Computed per MATTER, not per fee. On a commission matter the client's gross
 * payment is allocated to the revenue fee while the office-share line carries
 * the offsetting negative, so a per-fee view shows the revenue line as
 * over-collected and the deduction line as uncollected. Netting at matter level
 * makes those cancel, which is what actually happened:
 *
 *   owed     = SUM of every fee on the matter, signed — VAT adds, deduction
 *              lines subtract
 *   received = SUM of every allocation across those same fee lines
 *
 * Both sides must span the same fee lines. Counting only revenue fees as owed
 * while counting all allocations as received made every paid VAT or office-share
 * line look like over-collection of exactly its own amount.
 *
 * Each matter is aged by its oldest fee, since that is when the office first
 * became owed money on it.
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

        return ($user?->can('CollectFee:Matter') ?? false)
            || ($user?->can('ViewAny:IncentiveCalculation') ?? false);
    }

    protected function getData(): array
    {
        // Both sides span EVERY fee line on the matter, signed. Counting only
        // revenue fees as owed while counting all allocations as received made
        // each paid VAT or office-share line read as over-collection of exactly
        // its own amount.
        $owed = DB::table('fees')
            ->selectRaw('matter_id, SUM(amount) as owed, MIN(date) as first_billed')
            ->whereNotNull('date')
            ->groupBy('matter_id');

        // Received, per matter, netted across every fee line including deductions.
        $received = DB::table('allocations')
            ->join('fees', 'fees.id', '=', 'allocations.fee_id')
            ->selectRaw('fees.matter_id as matter_id, SUM(allocations.amount) as received')
            ->groupBy('fees.matter_id');

        $rows = DB::query()
            ->fromSub($owed, 'owed')
            ->join('matters', 'matters.id', '=', 'owed.matter_id')
            ->leftJoinSub($received, 'paid', 'paid.matter_id', '=', 'owed.matter_id')
            ->whereNull('matters.deleted_at')
            ->selectRaw('owed.first_billed, (owed.owed - COALESCE(paid.received, 0)) as outstanding')
            ->havingRaw('outstanding > 0.005')
            ->get();

        $buckets = [0.0, 0.0, 0.0, 0.0];

        foreach ($rows as $row) {
            // Billed date -> today, in that order: Carbon returns b - a, so the
            // reverse yields a negative age and drops everything into 0-30.
            $days = Carbon::parse($row->first_billed)->startOfDay()
                ->diffInDays(now()->startOfDay());

            $index = match (true) {
                $days <= 30 => 0,
                $days <= 60 => 1,
                $days <= 90 => 2,
                default => 3,
            };

            $buckets[$index] += (float) $row->outstanding;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Outstanding (AED)'),
                    'data' => array_map(fn (float $v) => round($v, 2), $buckets),
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
