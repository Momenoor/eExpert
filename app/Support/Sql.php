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

    /**
     * Whole days from one date column to another, as a sortable SQL expression.
     */
    public static function daysBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(julianday(date({$to})) - julianday(date({$from})) AS INTEGER)",
            'pgsql' => "({$to}::date - {$from}::date)",
            default => "DATEDIFF({$to}, {$from})",
        };
    }

    /**
     * A date column rendered as `YYYY-MM`, for grouping by month.
     */
    public static function yearMonth(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * A `YYYY-MM` period rendered as a sortable integer, e.g. 202608.
     *
     * Reports that group by month need a row key, and the period string cannot
     * be it: Eloquent casts a model's `id` to int, so '2026-08' arrives as 2026
     * and every month in the same year collapses onto one key.
     */
    public static function periodKey(string $column): string
    {
        $type = match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => 'INTEGER',
            default => 'UNSIGNED',
        };

        return "CAST(REPLACE({$column}, '-', '') AS {$type})";
    }

    /**
     * Does a JSON array-of-objects column hold an element matching every pair?
     *
     * `parties.role` stores `[{"role":"expert","type":"assistant","field":null}]`,
     * and the app asks "is this party an assistant expert?" in twenty places via
     * `whereJsonContains('role', ['role' => ..., 'type' => ...])`. That compiles
     * to JSON_CONTAINS on MySQL and works, but SQLite's grammar renders the same
     * call as a comparison against the element's whole JSON text, which never
     * matches and in practice throws — so every page built on it was impossible
     * to test.
     *
     * @param  array<string, string>  $pairs  JSON keys to required values
     * @return array{0: string, 1: list<string>} the SQL fragment and its bindings
     */
    public static function jsonArrayHas(string $column, array $pairs): array
    {
        $bindings = array_values($pairs);

        if (DB::connection()->getDriverName() === 'sqlite') {
            $conditions = implode(' AND ', array_map(
                fn (string $key): string => "json_extract(json_each.value, '$.{$key}') = ?",
                array_keys($pairs),
            ));

            return ["EXISTS (SELECT 1 FROM json_each({$column}) WHERE {$conditions})", $bindings];
        }

        return ["JSON_CONTAINS({$column}, ?)", [json_encode($pairs)]];
    }
}
