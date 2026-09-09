<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * All 14 report pages collapse into this one "Reports" sidebar entry instead
 * of each being its own top-level nav item — the report pages themselves stay
 * exactly where they are (app/Filament/Pages/Reports/), each just declares
 * `protected static ?string $cluster = Reports::class;` instead of a
 * navigationGroup. Deliberately ungrouped itself (no navigationGroup of its
 * own) — nesting it inside a "Reports" group would just read as "Reports >
 * Reports".
 */
class Reports extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('Reports');
    }
}
