<?php

/**
 * Explicit per-module file/directory manifest consumed by
 * `App\Services\Installer\ModulePruner`.
 *
 * Every path is relative to `base_path()`. Paths are listed explicitly
 * (not as wildcard globs) so a later change to either module's file layout
 * has to touch this list deliberately rather than silently pruning
 * something new that happens to match a pattern.
 *
 * A handful of files physically live under `app/Filament/Mms/**` but are
 * imported directly by `PmsPanelProvider` and must never be pruned along
 * with the rest of MMS — see the exclusions noted below.
 */

return [

    'mms' => [
        'paths' => [
            // Filament: resources/pages/widgets/clusters, excluding the
            // three files PmsPanelProvider imports directly.
            'app/Filament/Mms/Actions',
            'app/Filament/Mms/Clusters',
            'app/Filament/Mms/Concerns',
            'app/Filament/Mms/Exports',
            'app/Filament/Mms/Forms',
            'app/Filament/Mms/Imports',
            'app/Filament/Mms/Resources',
            'app/Filament/Mms/Widgets',
            'app/Filament/Mms/Pages/AccessControlMaintenance.php',
            'app/Filament/Mms/Pages/AdminDashboard.php',
            'app/Filament/Mms/Pages/Chat.php',
            'app/Filament/Mms/Pages/FeeDataMaintenance.php',
            'app/Filament/Mms/Pages/FinancialConfiguration.php',
            'app/Filament/Mms/Pages/FixMattersDifficulty.php',
            'app/Filament/Mms/Pages/Payroll',
            'app/Filament/Mms/Pages/Reports',
            'app/Filament/Mms/Pages/Schemas',
            'app/Filament/Mms/Pages/SystemSettings.php',
            // NOTE: app/Filament/Mms/Pages/Auth/CustomLogin.php,
            // app/Filament/Mms/Pages/Auth/CustomProfile.php, and
            // app/Filament/Mms/Support/SystemSwitcher.php are deliberately
            // NOT listed — PmsPanelProvider imports them directly, so
            // pruning MMS must leave them in place.

            // Models
            'app/Models/Matter.php',
            'app/Models/MatterMeta.php',
            'app/Models/MatterParty.php',
            'app/Models/MatterRequest.php',
            'app/Models/MatterLetter.php',
            'app/Models/MatterLetterRecipient.php',
            'app/Models/MatterFieldDefinition.php',
            'app/Models/MatterTypeIncentiveConfig.php',
            'app/Models/MatterTypeIncentiveTier.php',
            'app/Models/Fee.php',
            'app/Models/Allocation.php',
            'app/Models/Court.php',
            'app/Models/LetterTemplate.php',
            'app/Models/BulkMailCampaign.php',
            'app/Models/BulkMailLog.php',
            'app/Models/BulkMailRecipient.php',
            'app/Models/CalendarEvent.php',
            'app/Models/Type.php',
            'app/Models/EmployeeProfile.php',
            'app/Models/EmployeeLoan.php',
            'app/Models/EmployeeSalaryComponent.php',
            'app/Models/LoanInstallment.php',
            'app/Models/LeaveRequest.php',
            'app/Models/LeaveRequestPeriod.php',
            'app/Models/LeaveEntitlement.php',
            'app/Models/PartyLeave.php',
            'app/Models/PayrollRun.php',
            'app/Models/Payslip.php',
            'app/Models/PayslipLine.php',
            'app/Models/IncentiveCalculation.php',
            'app/Models/IncentiveLine.php',
            'app/Models/IncentiveLineDeduction.php',
            'app/Models/IncentiveExtraRule.php',
            'app/Models/IncentiveMetaAdjustment.php',
            'app/Models/IncentiveAssistantLine.php',
            'app/Models/IncentiveAssistantExtra.php',
            'app/Models/EosgAccrual.php',
            'app/Models/EosgClosingVoucher.php',
            'app/Models/EosgClosingVoucherLine.php',
            'app/Models/Note.php',
            'app/Models/ChatConversation.php',
            'app/Models/ChatConversationUser.php',
            'app/Models/ChatMessage.php',
            'app/Models/Attachment.php',

            // Services
            'app/Services/MMS',

            // Controllers
            'app/Http/Controllers/BulkMailController.php',
            'app/Http/Controllers/EndOfServiceGratuityClosingVoucherPrintController.php',
            'app/Http/Controllers/IncentiveCalculationAssistantPrintController.php',
            'app/Http/Controllers/IncentiveCalculationPrintController.php',
            'app/Http/Controllers/LeaveRequestEmailActionController.php',
            'app/Http/Controllers/MatterReceivedNotificationController.php',
            'app/Http/Controllers/PayrollJournalVoucherPrintController.php',
            'app/Http/Controllers/SalaryAuthorizationFormPrintController.php',

            // Console commands
            'app/Console/Commands/ConfirmMatterReceivingForUnacceptedMail.php',
            'app/Console/Commands/ResetIncentiveData.php',
            'app/Console/Commands/SendBulkCampaignsCommand.php',
            'app/Console/Commands/SyncMatterMetas.php',

            // Livewire
            'app/Livewire/ChatWidget.php',

            // Routes
            'routes/mms.php',

            // Blade views (no dedicated directory — listed individually,
            // cross-referenced from every MMS Filament class's `$view`/
            // `view()` usage; see the plan's Appendix B).
            'resources/views/filament/pages/access-control-maintenance.blade.php',
            'resources/views/filament/pages/chat.blade.php',
            'resources/views/filament/pages/fee-data-maintenance.blade.php',
            'resources/views/filament/pages/fix-matters-difficulty.blade.php',
            'resources/views/filament/pages/announcement.blade.php',
            'resources/views/filament/pages/assistant-performance-report.blade.php',
            'resources/views/filament/pages/assistant-matter-fees-report.blade.php',
            'resources/views/filament/pages/court-workload-report.blade.php',
            'resources/views/filament/pages/assistant-matters-report.blade.php',
            'resources/views/filament/pages/fee-collection-aging-report.blade.php',
            'resources/views/filament/pages/matters-monthly-report.blade.php',
            'resources/views/filament/pages/deductions-reconciliation-report.blade.php',
            'resources/views/filament/pages/matter-quality-report.blade.php',
            'resources/views/filament/pages/my-matters-report.blade.php',
            'resources/views/filament/pages/overdue-matters-report.blade.php',
            'resources/views/filament/pages/reports/my-incentive-report.blade.php',
            'resources/views/filament/pages/reports/partials/report-print-header.blade.php',
            'resources/views/filament/pages/vat-summary-report.blade.php',
            'resources/views/filament/pages/type-profitability-report.blade.php',
            'resources/views/filament/tables/columns/party-badges.blade.php',
            'resources/views/filament/payroll/journal-voucher.blade.php',
            'resources/views/filament/payroll/loan-breakdown.blade.php',
            'resources/views/filament/payroll/eosg-rollforward.blade.php',
            'resources/views/filament/forms/components/rich-editor/rich-content-custom-blocks/hero/preview.blade.php',
        ],
    ],

    'pms' => [
        'paths' => [
            // Filament: everything, no shared exceptions here.
            'app/Filament/Pms',

            // Models
            'app/Models/Property.php',
            'app/Models/Unit.php',
            'app/Models/Lease.php',
            'app/Models/LeaseParty.php',
            'app/Models/Tenant.php',
            'app/Models/Quotation.php',
            'app/Models/Installment.php',
            'app/Models/InstallmentPayment.php',
            'app/Models/OwnerGroup.php',
            'app/Models/OwnerGroupBankAccount.php',
            'app/Models/OwnerProfile.php',
            'app/Models/ConditionTemplate.php',
            'app/Models/ConditionTemplateItem.php',
            'app/Models/LeasePrintTemplate.php',
            'app/Models/LeasePrintTemplatePage.php',
            'app/Models/LeasePrintTemplateField.php',

            // Services
            'app/Services/PMS',

            // Controllers
            'app/Http/Controllers/LeaseFinancialDocumentsController.php',
            'app/Http/Controllers/LeasePrintController.php',
            'app/Http/Controllers/QuotationPrintController.php',

            // Console commands
            'app/Console/Commands/FlagOverdueInstallments.php',
            'app/Console/Commands/ResetPmsDemoData.php',

            // Livewire
            'app/Livewire/Pms/PrintTemplatePageBuilder.php',

            // Routes
            'routes/pms.php',

            // Blade views — cleanly namespaced, prune the whole directory.
            'resources/views/filament/pms',
        ],
    ],

];
