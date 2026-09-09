<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use App\Policies\EmployeeLoanPolicy;
use App\Policies\EmployeeProfilePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollRunPolicy;
use Database\Seeders\PayrollModulePermissionsSeeder;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every ability the module's policies check must actually be grantable.
 *
 * This exists because of a real failure. The five payroll approval abilities
 * were created and granted by the seeder, and then silently stripped from
 * super-admin when somebody opened Shield's role editor — which SYNCS a role's
 * permissions and only knows about abilities registered in
 * config/filament-shield.php. The visible symptom was that the "Generate
 * Payslips" button had vanished, so a newly created payroll run stayed empty
 * with no explanation.
 *
 * Three things have to agree for that not to recur: the policy, the seeder, and
 * Shield's config. These tests assert all three against each other.
 */
class PayrollPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The module's policies, and the model each one guards.
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function policyProvider(): array
    {
        return [
            'EmployeeProfile' => [EmployeeProfilePolicy::class, 'EmployeeProfile'],
            'LeaveRequest' => [LeaveRequestPolicy::class, 'LeaveRequest'],
            'EmployeeLoan' => [EmployeeLoanPolicy::class, 'EmployeeLoan'],
            'PayrollRun' => [PayrollRunPolicy::class, 'PayrollRun'],
        ];
    }

    /**
     * @param  class-string  $policy
     */
    #[DataProvider('policyProvider')]
    public function test_the_seeder_creates_and_grants_every_ability_the_policy_checks(string $policy, string $subject): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->seed(PayrollModulePermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        foreach (self::abilitiesOf($policy) as $ability) {
            // manageOthers deliberately resolves to Approve:LeaveRequest rather
            // than owning a permission of its own, so it has nothing to grant.
            if ($ability === 'manageOthers') {
                continue;
            }

            $permission = Str::ucfirst($ability).':'.$subject;

            $this->assertTrue(
                Permission::where('name', $permission)->where('guard_name', 'web')->exists(),
                "The seeder never creates {$permission}, so {$policy}::{$ability}() can never pass.",
            );

            $this->assertTrue(
                $admin->fresh()->can($permission),
                "super_admin is not granted {$permission}.",
            );
        }
    }

    /**
     * @param  class-string  $policy
     */
    #[DataProvider('policyProvider')]
    public function test_shield_knows_about_every_ability_beyond_its_standard_set(string $policy, string $subject): void
    {
        $standard = array_map(
            fn (string $method): string => strtolower($method),
            config('filament-shield.policies.methods', []),
        );

        $registered = collect(config('filament-shield.resources.manage', []))
            ->flatten()
            ->map(fn (string $ability): string => strtolower($ability))
            ->all();

        $abilities = self::abilitiesOf($policy);

        $this->assertNotEmpty($abilities, "{$policy} declares no abilities at all.");

        foreach ($abilities as $ability) {
            if ($ability === 'manageOthers' || in_array(strtolower($ability), $standard, true)) {
                continue;
            }

            // An ability Shield cannot see is one its role editor will strip
            // from every role the next time anybody saves one.
            $this->assertContains(
                strtolower($ability),
                $registered,
                "{$policy}::{$ability}() is not registered under resources.manage in config/filament-shield.php, so Shield's role editor will silently drop it.",
            );
        }
    }

    public function test_a_super_admin_can_actually_run_the_payroll_ladder(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->seed(PayrollModulePermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $run = PayrollRun::create(['period' => '2026-06', 'status' => 'draft']);

        // The exact checks the header actions make. Before the config fix, every
        // one of these was false for a super admin and the buttons simply were
        // not there.
        $this->assertTrue($admin->can('generate', $run));
        $this->assertTrue($admin->can('hrApprove', $run));
        $this->assertTrue($admin->can('financeApprove', $run));
        $this->assertTrue($admin->can('disburse', $run));
        $this->assertTrue($admin->can('viewJournalVoucher', $run));
    }

    /**
     * The abilities a policy actually declares.
     *
     * HandlesAuthorization contributes denyWithStatus() and friends, and a trait
     * method reports the USING class as its own, so those cannot be filtered out
     * by comparing class names — they have to be named and excluded.
     *
     * @param  class-string  $policy
     * @return list<string>
     */
    private static function abilitiesOf(string $policy): array
    {
        $fromTrait = collect((new ReflectionClass(HandlesAuthorization::class))->getMethods())
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->all();

        return collect((new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->class === $policy)
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->reject(fn (string $name): bool => in_array($name, $fromTrait, true))
            ->values()
            ->all();
    }
}
