<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shield permission labels (Arabic)
|--------------------------------------------------------------------------
|
| Laravel merges this file over the package's own ar/filament-shield.php, so
| only what is overridden or added needs to appear here.
|
| `shield:translation ar` writes to lang/ar/filament-shield.resource_permission_prefixes_labels.php,
| which Laravel never loads: it resolves a group by the first dot, so that
| filename is read as group "filament-shield", item
| "resource_permission_prefixes_labels...". Shield looks the labels up under its
| own namespace (filament-shield::filament-shield.…), which is this file.
|
| Keys are the snake_case form of the permission affix — Shield derives them
| with Utils::toLocalizationKey(), so `ViewAny` becomes `view_any` and the page
| permission `View:MyMattersReport` becomes `view_my_matters_report`.
|
*/

return [
    'resource_permission_prefixes_labels' => [

        // Standard resource affixes.
        'view' => 'عرض',
        'view_any' => 'عرض الكل',
        'create' => 'إضافة',
        'update' => 'تعديل',
        'delete' => 'حذف',
        'delete_any' => 'حذف الكل',
        'force_delete' => 'حذف نهائي',
        'force_delete_any' => 'حذف نهائي للكل',
        'restore' => 'استرجاع',
        'restore_any' => 'استرجاع الكل',
        'replicate' => 'استنساخ',
        'reorder' => 'إعادة ترتيب',

        // Matter — scope.
        'view_own' => 'عرض ملفاته فقط',
        'view_trashed' => 'عرض المحذوفات',
        'export' => 'تصدير',
        'import' => 'استيراد',

        // Matter — reports.
        'initial_report' => 'التقرير الأولي',
        'final_report' => 'التقرير النهائي',
        'update_initial_report_date' => 'تعديل تاريخ التقرير الأولي',
        'update_final_report_date' => 'تعديل تاريخ التقرير النهائي',
        'bulk_update_final_report_date' => 'تعديل جماعي لتاريخ التقرير النهائي',

        // Matter — notes.
        'create_note' => 'إضافة ملاحظة',
        'update_note' => 'تعديل ملاحظة',
        'delete_note' => 'حذف ملاحظة',

        // Matter — requests.
        'create_request' => 'إنشاء طلب',
        'approve_request' => 'اعتماد الطلب',
        'reject_request' => 'رفض الطلب',

        // Matter — fees and collections.
        'create_fee' => 'إضافة أتعاب',
        'update_fee' => 'تعديل الأتعاب',
        'delete_fee' => 'حذف الأتعاب',
        'collect_fee' => 'تحصيل الأتعاب',
        'update_allocation' => 'تعديل دفعة',
        'delete_allocation' => 'حذف دفعة',

        // Matter — attachments.
        'create_attachment' => 'إضافة مرفق',
        'delete_attachment' => 'حذف مرفق',

        // Calendar events.
        'create_single' => 'إنشاء موعد مفرد',
        'create_bulk' => 'إنشاء مواعيد مجمّعة',
        'import_from_outlook' => 'استيراد من Outlook',
        'sync_to_outlook' => 'المزامنة مع Outlook',

        // Pages.
        'view_admin_dashboard' => 'لوحة التحكم',
        'view_assistant_matter_fees_report' => 'تقرير أتعاب المساعدين',
        'view_assistant_matters_count' => 'عدد ملفات المساعدين',
        'view_assistant_matters_report' => 'تقرير ملفات المساعدين',
        'view_assistant_performance_report' => 'أداء المساعدين',
        'view_court_workload_report' => 'حجم العمل حسب المحكمة',
        'view_deductions_reconciliation_report' => 'مطابقة الخصومات',
        'view_fee_collection_aging_report' => 'تحصيل الأتعاب والتقادم',
        'view_fee_data_maintenance' => 'صيانة بيانات الأتعاب',
        'view_fix_matters_difficulty' => 'تصحيح صعوبة القضايا',
        'view_incentive_configuration' => 'تهيئة الحافز',
        'view_matter_quality_report' => 'الجودة وإعادة العمل',
        'view_matters_monthly_report' => 'تقرير القضايا الشهري',
        'view_my_matters_report' => 'قضاياي',
        'view_overdue_matters_report' => 'القضايا المتأخرة',
        'view_system_settings' => 'إعدادات النظام',
        'view_type_profitability_report' => 'الربحية حسب نوع القضية',
        'view_vat_summary_report' => 'ملخص ضريبة القيمة المضافة',

        // Widgets.
        'view_assistant_matter_count_table_widget' => 'جدول عدد ملفات المساعدين',
        'view_assistant_matters_count_chart_widget' => 'رسم عدد ملفات المساعدين',
        'view_attention_needed_widget' => 'يتطلب الانتباه',
        'view_calendar_widget' => 'التقويم',
        'view_collections_aging_widget' => 'تقادم التحصيل',
        'view_incentive_extra_rules_overview_widget' => 'نظرة عامة على قواعد النسبة الإضافية',
        'view_incentive_meta_adjustments_overview_widget' => 'نظرة عامة على تعديلات الحافز',
        'view_incentive_summary_table_widget' => 'جدول ملخص الحافز',
        'view_incentive_type_configs_overview_widget' => 'نظرة عامة على إعدادات الحافز حسب النوع',
        'view_matter_stats_widget' => 'إحصائيات القضايا',
        'view_matters_per_year_widget' => 'القضايا المستلمة سنويًا',
        'view_upcoming_sessions_widget' => 'الجلسات القادمة',
        'view_vacation_calendar_widget' => 'تقويم الإجازات',
    ],
];
