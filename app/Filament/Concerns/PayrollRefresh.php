<?php

namespace App\Filament\Concerns;

/**
 * The name of the event that keeps the payroll screens in step.
 *
 * A constant rather than a literal repeated across a dozen call sites, and a
 * class rather than a constant on RefreshesPayrollData because PHP will not let
 * anything read a constant off a trait — and half the dispatchers here are
 * table configurators, which are not components and cannot use the trait.
 */
final class PayrollRefresh
{
    public const EVENT = 'payroll-data-updated';
}
