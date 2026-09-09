<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a brand-new deployment needs before an operator can sign in and
 * start entering real data — and nothing else.
 *
 * This is what the installer wizard runs (Step 4), and it is deliberately
 * narrow. Three categories the wider task description named are answered by
 * NOT seeding them, on purpose:
 *
 * - Settings and payroll constants already have working code-level defaults —
 *   Setting::get('key', self::DEFAULT_X) throughout the incentive and payroll
 *   services — so the application runs correctly with zero rows in `settings`.
 *   Seeding them here would just be a second, easier-to-forget place for those
 *   defaults to drift out of sync with the code.
 * - Default lookups (Courts, Matter Types) are this office's own business data,
 *   not framework scaffolding. Inventing placeholder rows for a legal-practice
 *   management system would be fabricating data nobody asked for.
 *
 * What IS essential — and what actually breaks a fresh install without it — is
 * permissions and roles: Filament Shield's role editor and every policy in this
 * app check named permissions that have to exist before the first login, and
 * MatterPermissionsSeeder is the one place non-admin roles (expert, party,
 * accountant) get anything at all. Everything here is safe to run more than
 * once — every seeder in this list uses firstOrCreate().
 *
 * The administrator account itself is intentionally never created here. It is
 * created interactively by the installer's own Step 5, so this seeder never
 * needs — and never hands out — a hardcoded username or password.
 */
class ProductionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AllPermissionsSeeder::class,
            MatterPermissionsSeeder::class,
            PayrollModulePermissionsSeeder::class,
            CalendarEventPermissionsSeeder::class,
            IncentiveCalculationPermissionsSeeder::class,
        ]);
    }
}
