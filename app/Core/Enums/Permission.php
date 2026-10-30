<?php

namespace App\Core\Enums;

enum Permission: string
{
    case ManageCatalog = 'manage_catalog';
    case ApproveListing = 'approve_listing';
    case PublishListing = 'publish_listing';
    case OpenPrivateDocuments = 'open_private_documents';
    case RevealPhone = 'reveal_phone';
    case ManageCrm = 'manage_crm';
    case AdvanceCase = 'advance_case';
    case AdvanceFinanceStages = 'advance_finance_stages';
    case EditCommissions = 'edit_commissions';
    case ApproveCommissions = 'approve_commissions';
    case ViewCommissions = 'view_commissions';
    case ManageStaff = 'manage_staff';
    case ExportCsv = 'export_csv';
    case ViewUsers = 'view_users';
    case SuspendUser = 'suspend_user';
    case ApproveBroadcasts = 'approve_broadcasts';

    public function labelAr(): string
    {
        return match ($this) {
            self::ManageCatalog => 'تغيير الفئات ورقم المبيعات',
            self::ApproveListing => 'اعتماد العقارات',
            self::PublishListing => 'نشر / إلغاء نشر العقارات',
            self::OpenPrivateDocuments => 'فتح المستندات الخاصة',
            self::RevealPhone => 'عرض هواتف الأطراف (مسجّل)',
            self::ManageCrm => 'إدارة العملاء والطلبات',
            self::AdvanceCase => 'تحريك مراحل الحالات',
            self::AdvanceFinanceStages => 'تحريك المراحل المالية للحالة',
            self::EditCommissions => 'تعديل العمولات',
            self::ApproveCommissions => 'اعتماد تعديلات العمولة',
            self::ViewCommissions => 'عرض العمولات',
            self::ManageStaff => 'إدارة الموظفين والأدوار',
            self::ExportCsv => 'تصدير التقارير CSV',
            self::ViewUsers => 'عرض المستخدمين',
            self::SuspendUser => 'إيقاف وإعادة تفعيل المستخدمين',
            self::ApproveBroadcasts => 'اعتماد الإعلانات البثّية',
        };
    }
}
