<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * One shared "Date Range" filter for every report: a dropdown of named
 * presets (Today, This Month, Last Quarter, ...) plus a From/Until pair that
 * only appears for "Custom Range". Every report used to hand-roll its own
 * bare From/Until pair with no presets at all, each slightly differently.
 */
class ReportDateRangeFilter
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'today' => __('Today'),
            'yesterday' => __('Yesterday'),
            'this_week' => __('This Week'),
            'last_week' => __('Last Week'),
            'this_month' => __('This Month'),
            'last_month' => __('Last Month'),
            'this_quarter' => __('This Quarter'),
            'last_quarter' => __('Last Quarter'),
            'this_year' => __('This Year'),
            'last_year' => __('Last Year'),
            'custom' => __('Custom Range'),
        ];
    }

    /**
     * A blank preset with a from and/or until still filled is treated as
     * "custom" — the dropdown defaults to blank, so a caller (or a test) that
     * only ever fills the two date fields shouldn't also have to know to set
     * the preset to 'custom' first for them to take effect.
     *
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    public static function resolve(?string $preset, ?string $from = null, ?string $until = null): array
    {
        if (blank($preset) && (filled($from) || filled($until))) {
            $preset = 'custom';
        }

        $today = now()->startOfDay();

        return match ($preset) {
            'today' => [$today->copy(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'last_quarter' => [$today->copy()->subQuarterNoOverflow()->startOfQuarter(), $today->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'custom' => [
                filled($from) ? Carbon::parse($from)->startOfDay() : null,
                filled($until) ? Carbon::parse($until)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    /**
     * @param  string  $column  the date/datetime column to filter — pass a
     *                          fully qualified column (e.g. "matters.distributed_at")
     *                          when the report's base table is ambiguous.
     * @param  (callable(Builder, CarbonInterface|null, CarbonInterface|null): void)|null  $applyUsing
     *                                                                                                  override for reports where the date lives behind a relationship,
     *                                                                                                  a joined subquery alias, or needs anything beyond a plain
     *                                                                                                  ->whereDate() — receives the resolved [from, until] Carbon
     *                                                                                                  instances instead.
     * @param  string  $name  the filter's own key — kept overridable so a
     *                        report already tested against a specific
     *                        filterTable() name (e.g. 'period') doesn't need
     *                        its tests renamed just to gain the presets.
     */
    public static function make(string $column, ?string $label = null, ?callable $applyUsing = null, string $name = 'date_range'): Filter
    {
        $label ??= __('Date Range');

        return Filter::make($name)
            ->label($label)
            ->schema([
                Select::make('preset')
                    ->label($label)
                    ->options(self::options())
                    ->native(false)
                    ->live(),
                DatePicker::make('from')
                    ->label(__('From'))
                    ->visible(fn (Get $get) => $get('preset') === 'custom'),
                DatePicker::make('until')
                    ->label(__('Until'))
                    ->visible(fn (Get $get) => $get('preset') === 'custom'),
            ])
            ->columns(3)
            ->query(function (Builder $query, array $data) use ($column, $applyUsing) {
                [$from, $until] = self::resolve($data['preset'] ?? null, $data['from'] ?? null, $data['until'] ?? null);

                if (! $from && ! $until) {
                    return;
                }

                if ($applyUsing) {
                    $applyUsing($query, $from, $until);

                    return;
                }

                $query
                    ->when($from, fn ($q) => $q->whereDate($column, '>=', $from->toDateString()))
                    ->when($until, fn ($q) => $q->whereDate($column, '<=', $until->toDateString()));
            })
            ->indicateUsing(function (array $data) use ($label) {
                [$from, $until] = self::resolve($data['preset'] ?? null, $data['from'] ?? null, $data['until'] ?? null);

                if (! $from && ! $until) {
                    return null;
                }

                $preset = $data['preset'] ?? 'custom';

                if ($preset !== 'custom' && isset(self::options()[$preset])) {
                    return $label.': '.self::options()[$preset];
                }

                return $label.': '.($from?->toFormattedDateString() ?? '…').' – '.($until?->toFormattedDateString() ?? '…');
            });
    }
}
