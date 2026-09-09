<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Spatie\Permission\Models\Permission;

$arJsonPath = __DIR__.'/lang/ar.json';
$translations = json_decode(file_get_contents($arJsonPath), true);

$map = [
    // Legacy permissions
    'cash-collect' => 'تحصيل نقدي',
    'cash-delete' => 'حذف نقدي',
    'cash-update' => 'تعديل نقدي',
    'cash-view' => 'عرض نقدي',
    'claim-create' => 'إنشاء مطالبة',
    'claim-delete' => 'حذف مطالبة',
    'court-create' => 'إضافة محكمة',
    'court-delete' => 'حذف محكمة',
    'court-edit' => 'تعديل محكمة',
    'court-view' => 'عرض المحاكم',
    'expert-create' => 'إضافة خبير',
    'expert-delete' => 'حذف خبير',
    'expert-edit' => 'تعديل خبير',
    'expert-view' => 'عرض الخبراء',
    'matter-change-status' => 'تغيير حالة القضية',
    'matter-create' => 'إضافة قضية',
    'matter-delete' => 'حذف قضية',
    'matter-distributing' => 'توزيع القضايا',
    'matter-edit' => 'تعديل قضية',
    'matter-export' => 'تصدير القضايا',
    'matter-only-own-view' => 'عرض قضاياه فقط',
    'matter-view' => 'عرض القضايا',
    'party-add-to-matter' => 'إضافة طرف للقضية',
    'party-create' => 'إضافة طرف',
    'party-delete' => 'حذف طرف',
    'party-edit' => 'تعديل طرف',
    'party-link-to-party' => 'ربط طرف بطرف',
    'party-unlink' => 'إلغاء ربط الطرف',
    'party-view' => 'عرض الأطراف',
    'permission-create' => 'إضافة صلاحية',
    'permission-delete' => 'حذف صلاحية',
    'permission-edit' => 'تعديل صلاحية',
    'permission-view' => 'عرض الصلاحيات',
    'role-create' => 'إضافة دور',
    'role-delete' => 'حذف دور',
    'role-edit' => 'تعديل دور',
    'role-view' => 'عرض الأدوار',
    'type-create' => 'إضافة نوع',
    'type-delete' => 'حذف نوع',
    'type-edit' => 'تعديل نوع',
    'type-view' => 'عرض الأنواع',
    'user-create' => 'إضافة مستخدم',
    'user-delete' => 'حذف مستخدم',
    'user-edit' => 'تعديل مستخدم',
    'user-view' => 'عرض المستخدمين',
    'vacation-create' => 'إضافة إجازة',
    'vacation-delete' => 'حذف إجازة',
    'vacation-edit' => 'تعديل إجازة',
    'vacation-view' => 'عرض الإجازات',

    // Action/Subject permissions
    'Approve:EmployeeLoan' => 'اعتماد سلفة الموظف',
    'Approve:LeaveRequest' => 'اعتماد طلب الإجازة',
    'ApproveRequest:Matter' => 'اعتماد طلب القضية',
    'BulkUpdateFinalReportDate:Matter' => 'تعديل جماعي لتاريخ التقرير النهائي',
    'CreateMatterRequest:MatterRequest' => 'إنشاء طلب قضية',
    'EditRequest:MatterRequest' => 'تعديل طلب قضية',
    'DeleteAny:BulkMailCampaign' => 'حذف حملات البريد الجماعي',
    'DeleteAny:IncentiveMetaAdjustment' => 'حذف تعديلات الحوافز',
    'DeleteAny:LetterTemplate' => 'حذف قوالب الخطابات',
    'delete_any_bulk_mail_campaign' => 'حذف حملات البريد الجماعي',
    'DeleteAnyBulkMailCampaign' => 'حذف حملات البريد الجماعي',
    'Disburse:PayrollRun' => 'صرف مسير الرواتب',
    'Finalize:IncentiveCalculation' => 'اعتماد نهائي لاحتساب الحافز',
    'FinanceApprove:PayrollRun' => 'اعتماد المالية لمسير الرواتب',
    'Generate:PayrollRun' => 'توليد مسير الرواتب',
    'HrApprove:PayrollRun' => 'اعتماد الموارد البشرية لمسير الرواتب',
    'Print:IncentiveCalculation' => 'طباعة احتساب الحافز',
    'RunCalculation:IncentiveCalculation' => 'تشغيل احتساب الحافز',
    'Send:BulkMailCampaign' => 'إرسال حملة البريد الجماعي',
    'send_bulk_mail_campaign' => 'إرسال حملة البريد الجماعي',
    'SendBulkMailCampaign' => 'إرسال حملة البريد الجماعي',
    'ViewJournalVoucher:PayrollRun' => 'عرض قيد اليومية لمسير الرواتب',
];

// Entity Arabic names
$entityNames = [
    'Activity' => 'الأنشطة',
    'BulkMailCampaign' => 'حملات البريد الجماعي',
    'CalendarEvent' => 'مواعيد التقويم',
    'City' => 'المدن',
    'Court' => 'المحاكم',
    'Distributor' => 'الموزعين',
    'EmployeeLoan' => 'سلف الموظفين',
    'EmployeeProfile' => 'ملفات الموظفين',
    'IncentiveCalculation' => 'احتساب الحوافز',
    'IncentiveMetaAdjustment' => 'تعديلات الحوافز',
    'IncentiveSetting' => 'إعدادات الحوافز',
    'LeaveRequest' => 'طلبات الإجازات',
    'LetterTemplate' => 'قوالب الخطابات',
    'Matter' => 'القضايا',
    'MatterRequest' => 'طلبات القضايا',
    'Party' => 'الأطراف',
    'PartyLeave' => 'إجازات الأطراف',
    'PayrollRun' => 'مسيرات الرواتب',
    'Request' => 'الطلبات',
    'Role' => 'الأدوار',
    'Type' => 'الأنواع',
    'User' => 'المستخدمين',
];

$singularEntities = [
    'Activity' => 'النشاط',
    'BulkMailCampaign' => 'حملة البريد الجماعي',
    'CalendarEvent' => 'موعد التقويم',
    'City' => 'المدينة',
    'Court' => 'المحكمة',
    'Distributor' => 'الموزع',
    'EmployeeLoan' => 'سلفة الموظف',
    'EmployeeProfile' => 'ملف الموظف',
    'IncentiveCalculation' => 'احتساب الحافز',
    'IncentiveMetaAdjustment' => 'تعديل الحافز',
    'IncentiveSetting' => 'إعداد الحافز',
    'LeaveRequest' => 'طلب الإجازة',
    'LetterTemplate' => 'قالب الخطاب',
    'Matter' => 'القضية',
    'MatterRequest' => 'طلب القضية',
    'Party' => 'الطرف',
    'PartyLeave' => 'إجازة الطرف',
    'PayrollRun' => 'مسير الرواتب',
    'Request' => 'الطلب',
    'Role' => 'الدور',
    'Type' => 'النوع',
    'User' => 'المستخدم',
];

$actionLabels = [
    'ViewAny' => 'عرض كل',
    'View' => 'عرض',
    'Create' => 'إضافة',
    'Update' => 'تعديل',
    'Delete' => 'حذف',
    'DeleteAny' => 'حذف جميع',
    'ForceDelete' => 'حذف نهائي لـ',
    'ForceDeleteAny' => 'حذف نهائي لجميع',
    'Restore' => 'استرجاع',
    'RestoreAny' => 'استرجاع جميع',
    'Replicate' => 'استنساخ',
    'Reorder' => 'إعادة ترتيب',
];

foreach (Permission::pluck('name') as $pName) {
    if (isset($map[$pName])) {
        $translations[$pName] = $map[$pName];

        continue;
    }

    if (str_contains($pName, ':')) {
        [$action, $subject] = explode(':', $pName, 2);
        if (isset($actionLabels[$action])) {
            $isAny = str_ends_with($action, 'Any');
            $subjectLabel = $isAny ? ($entityNames[$subject] ?? $subject) : ($singularEntities[$subject] ?? $subject);
            $translations[$pName] = $actionLabels[$action].' '.$subjectLabel;
        }
    }
}

foreach ($map as $k => $v) {
    $translations[$k] = $v;
}

// Sort keys alphabetically for clean diff
ksort($translations);

file_put_contents($arJsonPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo "Successfully updated lang/ar.json with all permission translations.\n";
