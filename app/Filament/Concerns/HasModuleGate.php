<?php

namespace App\Filament\Concerns;

/**
 * Hides and blocks a Filament resource whose sub-module the installer's
 * Modules step left disabled — the panel itself (MMS) still boots, only
 * this one resource's navigation item and direct-URL access are gated.
 * The underlying table still exists (migrations always run in full; see
 * `config/modules.php`), so re-enabling the module later needs nothing
 * more than flipping the flag back on.
 */
trait HasModuleGate
{
    /**
     * The `config('modules.*')` key this resource is gated behind, e.g.
     * `mms_payroll`. Each resource using this trait declares its own.
     */
    abstract public static function moduleGateKey(): string;

    public static function isModuleEnabled(): bool
    {
        return (bool) config('modules.'.static::moduleGateKey(), true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isModuleEnabled() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return static::isModuleEnabled() && parent::canAccess();
    }
}
