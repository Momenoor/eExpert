<?php

namespace App\Filament\Pages;

use App\Services\AccessControlRepairService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Operator-driven role/permission repairs.
 *
 * Deliberately NOT a migration or a seeder: these change who can do what, so
 * each one is a button an administrator presses after reading exactly what it
 * will grant. Every figure is computed live against this database.
 *
 * Both repairs only ever GRANT — nothing is revoked — so a mistaken run cannot
 * lock anyone out, and running one twice changes nothing the second time.
 */
class AccessControlMaintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.access-control-maintenance';

    public static function getNavigationLabel(): string
    {
        return __('Access Control Maintenance');
    }

    public function getTitle(): string
    {
        return __('Access Control Maintenance');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'super_admin']) ?? false;
    }

    private function repairs(): AccessControlRepairService
    {
        return app(AccessControlRepairService::class);
    }

    public function content(Schema $schema): Schema
    {
        $repairs = $this->repairs();
        $alignment = $repairs->previewSuperAdminAlignment();
        $viewAccess = $repairs->previewViewAccess();
        $decisions = $repairs->unreachableNeedingDecision();
        $orphans = $repairs->orphanedPagePermissions();

        return $schema->components([
            Section::make(__('Super Administrator Roles'))
                ->description(__('Shield registers no gate for the super admin role here (define_via_gate is off), so a super admin can only do what is explicitly granted. Two such roles exist and they have drifted apart.'))
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->columns(2)
                ->schema([
                    TextEntry::make('super_admin_roles')
                        ->label(__('Roles treated as super admin'))
                        ->state($repairs->superAdminRoles()
                            ->map(fn ($role) => $role->name.' ('.$role->users()->count().')')
                            ->implode(', ') ?: __('None found')),

                    TextEntry::make('super_admin_missing')
                        ->label(__('Permissions they are missing'))
                        ->state($alignment['missing'].'  ('.collect($alignment['by_role'])
                            ->map(fn ($n, $role) => "{$role}: {$n}")
                            ->implode(', ').')')
                        ->badge()
                        ->color($alignment['missing'] > 0 ? 'danger' : 'success'),
                ]),

            Section::make(__('Abilities Nobody Can Reach'))
                ->description(__('A role that can act on something but cannot open it holds a dead permission — the button lives on a page it is refused entry to.'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->columns(2)
                ->schema([
                    TextEntry::make('unreachable')
                        ->label(__('Repairable'))
                        ->state($viewAccess['rows'].'  ('.$viewAccess['grants'].' '.__('permissions to grant').')')
                        ->badge()
                        ->color($viewAccess['rows'] > 0 ? 'warning' : 'success'),

                    TextEntry::make('needs_decision')
                        ->label(__('Needs your decision'))
                        ->state($decisions->isEmpty()
                            ? __('None')
                            : $decisions->map(fn ($row) => $row['role'].' → '.$row['subject'])->implode(', '))
                        ->helperText(__('These subjects have a scoped view ability, so granting full view rights could widen what the role sees. Decide these in the Roles screen.'))
                        ->badge()
                        ->color($decisions->isEmpty() ? 'success' : 'warning'),
                ]),

            Section::make(__('Stale Permissions'))
                ->description(__('Permissions whose page or widget class no longer exists. Reported only — a name here may belong to a panel this environment does not register, so nothing is deleted automatically.'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->schema([
                    TextEntry::make('orphans')
                        ->label(__('Orphaned page or widget permissions'))
                        ->state($orphans->isEmpty() ? __('None') : $orphans->implode(', '))
                        ->badge()
                        ->color($orphans->isEmpty() ? 'success' : 'gray'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('alignSuperAdminRoles')
                ->label(__('1. Align Super Admin Roles'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Align Super Admin Roles'))
                ->modalDescription(function () {
                    $p = $this->repairs()->previewSuperAdminAlignment();

                    if ($p['missing'] === 0) {
                        return __('Nothing to align — every super admin role already holds every permission.');
                    }

                    return __('Grants :missing missing permission(s) across :roles super admin role(s), so both hold everything. Nothing is revoked. This is what currently leaves a super admin able to open an incentive calculation but not run, finalize or print it.', [
                        'missing' => $p['missing'],
                        'roles' => $p['roles'],
                    ]);
                })
                ->modalSubmitActionLabel(__('Grant permissions'))
                ->action(function () {
                    $r = $this->repairs()->alignSuperAdminRoles();

                    Notification::make()
                        ->title($r['granted'] > 0 ? __('Super admin roles aligned') : __('Nothing to align'))
                        ->body(__(':granted permission(s) granted across :roles role(s).', [
                            'granted' => $r['granted'],
                            'roles' => $r['roles'],
                        ]))
                        ->success()
                        ->send();
                }),

            Action::make('grantMissingViewAccess')
                ->label(__('2. Repair Unreachable Abilities'))
                ->icon(Heroicon::OutlinedKey)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Repair Unreachable Abilities'))
                ->modalDescription(function () {
                    $rows = $this->repairs()->unreachableAbilities()->where('safe', true);

                    if ($rows->isEmpty()) {
                        return __('Nothing to repair — every role can open what it is allowed to act on.');
                    }

                    return __('Grants viewing rights so these roles can reach what they already have abilities for: :list. Nothing else changes.', [
                        'list' => $rows->map(fn ($row) => $row['role'].' → '.$row['subject'])->implode('; '),
                    ]);
                })
                ->modalSubmitActionLabel(__('Grant viewing rights'))
                ->action(function () {
                    $r = $this->repairs()->grantMissingViewAccess();

                    Notification::make()
                        ->title($r['grants'] > 0 ? __('Viewing rights granted') : __('Nothing to repair'))
                        ->body(__(':grants permission(s) granted across :rows role/subject pair(s).', [
                            'grants' => $r['grants'],
                            'rows' => $r['rows'],
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
