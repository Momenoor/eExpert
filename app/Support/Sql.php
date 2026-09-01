<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Small portability helpers for raw SQL fragments.
 *
 * The app runs on MySQL but the test suite runs on SQLite, so MySQL-only
 * functions make a query structurally untestable — a gap that already left the
 * monthly report with no coverage at all. Anything raw and date-related that a
 * report needs to sort or filter on belongs here instead.
 */
class Sql
{
    /**
     * Whole days between a date column and today, as a sortable SQL expression.
     */
    public static function daysSince(string $column): string
    {
        // Date-only on both sides, matching MySQL's DATEDIFF: julianday('now')
        // carries the time of day, so a span of exactly 100 days truncates to 99
        // for most of the day.
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(julianday(date('now')) - julianday(date({$column})) AS INTEGER)",
            'pgsql' => "(CURRENT_DATE - {$column}::date)",
            default => "DATEDIFF(CURDATE(), {$column})",
        };
    }
}
