<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Token-based search shared by the matter tables.
 *
 * This logic previously existed as two copy-pasted blocks — in MattersTable and
 * IncentiveSummaryTableWidget — which had already drifted apart: only the widget
 * copy handled multi-level relation paths, so the same search string behaved
 * differently depending on which table you typed it into. This is the widget's
 * (correct) version, kept in one place.
 */
trait HasMultiWordSearch
{
    /**
     * Split a search string into tokens on whitespace, slashes and dashes, so
     * "70/2026", "70 2026" and "2026-70" all yield the same two tokens.
     *
     * @return list<string>
     */
    protected static function splitSearch(string $search): array
    {
        return $search
            |> trim(...)
            |> (fn ($x) => preg_split('/[\s\/\\\\\-]+/', $x))
            |> (fn ($x) => array_filter($x, fn ($token) => strlen($token) > 0))
            |> array_values(...);
    }

    /**
     * Require every token to match at least one of the given columns.
     *
     * A column may be a dotted relation path of any depth
     * (e.g. incentiveLine.matter.type.name).
     *
     * @param  list<string>  $columns
     */
    protected static function applyMultiWordSearch(Builder $query, string $search, array $columns): Builder
    {
        foreach (static::splitSearch($search) as $token) {
            $query->where(function (Builder $query) use ($token, $columns) {
                foreach ($columns as $i => $column) {
                    if (! str_contains($column, '.')) {
                        $query->{$i === 0 ? 'where' : 'orWhere'}($column, 'like', "%{$token}%");

                        continue;
                    }

                    // Split on the LAST dot: Eloquent's whereHas traverses a
                    // dotted relation chain of any depth natively, and only the
                    // final segment is a real column to filter on. Splitting on
                    // the first dot (as one of the old copies did) broke every
                    // path deeper than one level.
                    $lastDot = strrpos($column, '.');

                    $query->{$i === 0 ? 'whereHas' : 'orWhereHas'}(
                        substr($column, 0, $lastDot),
                        fn (Builder $relation) => $relation->where(substr($column, $lastDot + 1), 'like', "%{$token}%")
                    );
                }
            });
        }

        return $query;
    }
}
