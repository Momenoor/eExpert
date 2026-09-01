<?php

namespace App\Services;

use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Repairs for role/permission drift.
 *
 * Like the fee repairs, every method comes in a preview/apply pair: the preview
 * is read-only and reports exactly what the apply would grant, so an
 * administrator reads the list before committing. Nothing runs automatically.
 *
 * Both repairs only ever GRANT. Nothing is revoked, so a mistaken run cannot
 * lock anybody out, and running twice changes nothing the second time.
 */
class AccessControlRepairService
{
    /**
     * Roles that are meant to be super administrators.
     *
     * The database carries two: `super_admin`, which the Shield config names and
     * which has no users, and `super-admin`, which the actual administrators
     * hold. They have drifted apart — the underscore one collected the custom
     * abilities, the hyphen one the standard ones. Because
     * `super_admin.define_via_gate` is false, Shield registers no Gate::before
     * for either, so neither gets anything implicitly: whatever a super admin
     * can do, it can do because the permission is attached.
     *
     * @return Collection<int, Role>
     */
    public function superAdminRoles(): Collection
    {
        $configured = Utils::getSuperAdminName();
        $variants = array_unique([
            $configured,
            str_replace('_', '-', $configured),
            str_replace('-', '_', $configured),
        ]);

        return Role::query()->whereIn('name', $variants)->get();
    }

    /**
     * Permissions each super-admin role is missing.
     *
     * @return array{roles: int, missing: int, by_role: array<string, int>}
     */
    public function previewSuperAdminAlignment(): array
    {
        $all = Permission::query()->pluck('name', 'id');
        $byRole = [];

        foreach ($this->superAdminRoles() as $role) {
            $held = $role->permissions->pluck('id');
            $missing = $all->keys()->diff($held)->count();

            if ($missing > 0) {
                $byRole[$role->name] = $missing;
            }
        }

        return [
            'roles' => count($byRole),
            'missing' => array_sum($byRole),
            'by_role' => $byRole,
        ];
    }

    /**
     * @return array{roles: int, granted: int}
     */
    public function alignSuperAdminRoles(): array
    {
        $all = Permission::query()->pluck('id');
        $granted = 0;
        $roles = 0;

        DB::transaction(function () use ($all, &$granted, &$roles) {
            foreach ($this->superAdminRoles() as $role) {
                $missing = $all->diff($role->permissions->pluck('id'));

                if ($missing->isEmpty()) {
                    continue;
                }

                $role->permissions()->attach($missing->all());
                $granted += $missing->count();
                $roles++;
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['roles' => $roles, 'granted' => $granted];
    }

    /**
     * Roles that can act on a subject but cannot open it.
     *
     * Shield names permissions `Ability:Subject`. A role holding, say,
     * `RunCalculation:IncentiveCalculation` without `ViewAny:IncentiveCalculation`
     * can never reach the page the action lives on, so the ability is dead — which
     * is exactly the state the incentive resource was in: `admin` could run,
     * finalize and print a calculation but could not list or open one, while
     * `super-admin` could open one and do nothing with it.
     *
     * A row is only marked `safe` when granting plain viewing rights cannot
     * widen what the role sees. If the subject defines a SCOPED view ability —
     * `ViewOwn:Matter`, say — then "cannot open it" and "should be able to open
     * all of them" are different questions, and which one applies is a decision
     * about that role's job, not a repair. Those rows are reported and left
     * alone.
     *
     * @return Collection<int, array{role: string, role_id: int|string, subject: string, grant: array<int, mixed>, safe: bool}>
     */
    public function unreachableAbilities(): Collection
    {
        $byName = Permission::query()->get(['id', 'name'])->keyBy('name');

        $rows = Role::with('permissions')->get()
            ->flatMap(function (Role $role) use ($byName) {
                $held = $role->permissions->pluck('name');

                return $held
                    ->filter(fn (string $name) => str_contains($name, ':'))
                    ->groupBy(fn (string $name) => explode(':', $name, 2)[1])
                    ->map(function (Collection $names, string $subject) use ($role, $held, $byName) {
                        $abilities = $names->map(fn (string $n) => explode(':', $n, 2)[0]);

                        // Any View-prefixed ability — View, ViewAny, ViewOwn,
                        // ViewTrashed — means the role can already reach the
                        // subject somehow. Nothing to repair.
                        if ($abilities->contains(fn (string $a) => str_starts_with($a, 'View'))) {
                            return null;
                        }

                        $grant = collect(['ViewAny', 'View'])
                            ->map(fn (string $a) => $a.':'.$subject)
                            ->reject(fn (string $name) => $held->contains($name))
                            // Only grant a permission that actually exists.
                            ->filter(fn (string $name) => $byName->has($name))
                            ->values();

                        if ($grant->isEmpty()) {
                            return null;
                        }

                        return [
                            'role' => $role->name,
                            'role_id' => $role->id,
                            'subject' => $subject,
                            'grant' => $grant->all(),
                            'safe' => ! $byName->has('ViewOwn:'.$subject),
                        ];
                    })
                    ->filter()
                    ->values();
            });

        // A plain collection, not the Eloquent one flatMap hands back: these are
        // arrays describing roles, not role models.
        return collect($rows->all());
    }

    /**
     * @return array{rows: int, grants: int}
     */
    public function previewViewAccess(): array
    {
        $rows = $this->unreachableAbilities()->where('safe', true);

        return [
            'rows' => $rows->count(),
            'grants' => $rows->sum(fn (array $row) => count($row['grant'])),
        ];
    }

    /**
     * Unreachable abilities that need a human decision rather than a repair.
     *
     * @return Collection<int, array{role: string, role_id: int|string, subject: string, grant: array<int, mixed>, safe: bool}>
     */
    public function unreachableNeedingDecision(): Collection
    {
        return $this->unreachableAbilities()->where('safe', false)->values();
    }

    /**
     * @return array{rows: int, grants: int}
     */
    public function grantMissingViewAccess(): array
    {
        $rows = $this->unreachableAbilities()->where('safe', true)->values();

        if ($rows->isEmpty()) {
            return ['rows' => 0, 'grants' => 0];
        }

        $ids = Permission::query()->pluck('id', 'name');
        $grants = 0;

        DB::transaction(function () use ($rows, $ids, &$grants) {
            foreach ($rows->groupBy('role_id') as $roleId => $roleRows) {
                $role = Role::find($roleId);

                if (! $role) {
                    continue;
                }

                $attach = collect($roleRows)
                    ->flatMap(fn (array $row) => $row['grant'])
                    ->map(fn (string $name) => $ids->get($name))
                    ->filter()
                    ->unique()
                    ->diff($role->permissions->pluck('id'));

                if ($attach->isEmpty()) {
                    continue;
                }

                $role->permissions()->attach($attach->all());
                $grants += $attach->count();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['rows' => $rows->count(), 'grants' => $grants];
    }

    /**
     * Permissions whose Filament page/widget class no longer exists.
     *
     * Shield derives a page or widget permission from the class basename, so a
     * deleted page leaves `View:ThatPage` behind forever. Reported, never
     * removed automatically — a name that looks orphaned here may simply belong
     * to a panel this environment does not register.
     *
     * @return Collection<int, string>
     */
    public function orphanedPagePermissions(): Collection
    {
        $live = collect(Filament::getPanel('admin')->getPages())
            ->merge(Filament::getPanel('admin')->getWidgets())
            ->map(fn (string $class) => 'View:'.class_basename($class));

        $subjects = Permission::query()
            ->where('name', 'like', 'View:%')
            ->pluck('name');

        // A resource's View permission is named for its MODEL, so keep any name
        // that matches a model-backed subject.
        $resourceSubjects = collect(Filament::getPanel('admin')->getResources())
            ->map(fn (string $resource) => 'View:'.class_basename($resource::getModel()));

        return $subjects
            ->reject(fn (string $name) => $live->contains($name) || $resourceSubjects->contains($name))
            ->values();
    }
}
