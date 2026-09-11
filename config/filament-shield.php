<?php

declare(strict_types=1);
use App\Filament\Resources\BulkMailCampaigns\BulkMailCampaignResource;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\IncentiveMetaAdjustmentResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LetterTemplates\LetterTemplateResource;
use App\Filament\Resources\Matters\MatterResource;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Resource
    |--------------------------------------------------------------------------
    |
    | Here you may configure the built-in role management resource. You can
    | customize the URL, choose whether to show model paths, group it under
    | a cluster, and decide which permission tabs to display.
    |
    */

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | When your application supports teams, Shield will automatically detect
    | and configure the tenant model during setup. This enables tenant-scoped
    | roles and permissions throughout your application.
    |
    */

    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This value contains the class name of your user model. This model will
    | be used for role assignments and must implement the HasRoles trait
    | provided by the Spatie\Permission package.
    |
    */

    'auth_provider_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Here you may define a super admin that has unrestricted access to your
    | application. You can choose to implement this via Laravel's gate system
    | or as a traditional role with all permissions explicitly assigned.
    |
    */

    'super_admin' => [
        'enabled' => true,
        // 'super-admin' (hyphenated), not 'super_admin'. The two roles drifted
        // apart in this database — 'super_admin' is what earlier config pointed
        // at and has zero users; 'super-admin' is what the real administrators
        // actually hold. Pointing Shield at the role people are actually on,
        // rather than migrating two live user accounts onto a different role,
        // is the safe direction for that fix — a config value is reversible, a
        // live role reassignment is not.
        'name' => 'super-admin',
        // Was false: every one of this role's 387 permissions had to be granted
        // explicitly (via AllPermissionsSeeder), and the two spellings drifted
        // to different permission counts as a result (see
        // AccessControlRepairService). Gate::before is Shield's own built-in
        // bypass — true here means a user holding 'super-admin' passes every
        // ability check unconditionally, which is what "super administrator"
        // is supposed to mean, and it is what lets every hardcoded
        // hasAnyRole(['super-admin', 'super_admin']) check scattered through
        // this app collapse into a single ordinary permission check.
        'define_via_gate' => true,
        'intercept_gate' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel User
    |--------------------------------------------------------------------------
    |
    | When enabled, Shield will create a basic panel user role that can be
    | assigned to users who should have access to your Filament panels but
    | don't need any specific permissions beyond basic authentication.
    |
    */

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Builder
    |--------------------------------------------------------------------------
    |
    | You can customize how permission keys are generated to match your
    | preferred naming convention and organizational standards. Shield uses
    | these settings when creating permission names from your resources.
    |
    | Supported formats: snake, kebab, pascal, camel, upper_snake, lower_snake
    |
    */

    'permissions' => [
        'separator' => ':',
        'case' => 'pascal',
        'generate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Shield can automatically generate Laravel policies for your resources.
    | When merge is enabled, the methods below will be combined with any
    | resource-specific methods you define in the resources section.
    |
    */

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Shield supports multiple languages out of the box. When enabled, you
    | can provide translated labels for permissions to create a more
    | localized experience for your international users.
    |
    */

    'localization' => [
        'enabled' => true,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Here you can fine-tune permissions for specific Filament resources.
    | Use the 'manage' array to override the default policy methods for
    | individual resources, giving you granular control over permissions.
    |
    */

    'resources' => [
        'subject' => 'model',
        'manage' => [
            // Abilities beyond Shield's standard prefixes. Anything a policy
            // checks but that is NOT listed here is invisible to the role
            // editor — and because that editor SYNCS a role's permissions, it
            // silently strips every unlisted grant the next time anyone saves a
            // role. That is not hypothetical: the payroll module's five approval
            // abilities were granted by seeder and wiped nineteen seconds before
            // the first payroll run, which is why nobody could press Generate.
            EmployeeLoanResource::class => [
                'approve',
            ],
            LeaveRequestResource::class => [
                'approve',
            ],
            PayrollRunResource::class => [
                'generate',
                'hrApprove',
                'financeApprove',
                'disburse',
                'viewJournalVoucher',
            ],
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
            MatterResource::class => [
                'deleteAny',
                'viewOwn',
                'viewTrashed',
                'updateInitialReportDate',
                'updateFinalReportDate',
                'bulkUpdateFinalReportDate',
                'export',
                'import',
                'initialReport',
                'finalReport',

                // Notes
                'createNote',
                'updateNote',
                'deleteNote',

                // Requests
                'createRequest',
                'approveRequest',
                'rejectRequest',

                // Fees
                'createFee',
                'updateFee',
                'deleteFee',

                // Payments / Allocations
                'collectFee',
                'updateAllocation',
                'deleteAllocation',

                // Attachments
                'createAttachment',
                'deleteAttachment',
            ],
            CalendarEventResource::class => [
                'createSingle',
                'createBulk',
                'importFromOutlook',
                'syncToOutlook',
            ],

            IncentiveCalculationResource::class => [
                'runCalculation',
                'finalize',
                'print',
            ],
            BulkMailCampaignResource::class => [
                'deleteAny',
                'send',
            ],
            IncentiveMetaAdjustmentResource::class => [
                'deleteAny',
            ],
            LetterTemplateResource::class => [
                'deleteAny',
            ],
        ],
        'exclude' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Most Filament pages only require view permissions. Pages listed in the
    | exclude array will be skipped during permission generation and won't
    | appear in your role management interface.
    |
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Like pages, widgets typically only need view permissions. Add widgets
    | to the exclude array if you don't want them to appear in your role
    | management interface.
    |
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Sometimes you need permissions that don't map to resources, pages, or
    | widgets. Define any custom permissions here and they'll be available
    | when editing roles in your application.
    |
    */

    'custom_permissions' => [
        // EosgClosingVoucher has no Filament Resource — it's a plain model
        // written to only from the EndOfServiceGratuityClosingVoucher page and
        // its service, so its two abilities have nowhere else to be declared.
        // Without this, the role editor cannot see them and, because it SYNCS
        // a role's permissions on save, would strip them from every role the
        // next time anyone saved one — see the PayrollRun caution above.
        'View:EosgClosingVoucher',
        'Generate:EosgClosingVoucher',
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery
    |--------------------------------------------------------------------------
    |
    | By default, Shield only looks for entities in your default Filament
    | panel. Enable these options if you're using multiple panels and want
    | Shield to discover entities across all of them.
    |
    */

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Policy
    |--------------------------------------------------------------------------
    |
    | Shield can automatically register a policy for role management itself.
    | This lets you control who can manage roles using Laravel's built-in
    | authorization system. Requires a RolePolicy class in your app.
    |
    */

    'register_role_policy' => true,

];
