<?php

namespace App\Core\Enums;

enum StaffRole: string
{
    case SuperAdmin = 'super_admin';
    case Operations = 'operations';
    case Reviewer = 'reviewer';
    case Sales = 'sales';
    case Finance = 'finance';

    public function labelAr(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدير عام',
            self::Operations => 'عمليات',
            self::Reviewer => 'مراجع',
            self::Sales => 'مبيعات',
            self::Finance => 'مالية',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Operations => 'Operations',
            self::Reviewer => 'Reviewer',
            self::Sales => 'Sales',
            self::Finance => 'Finance',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Operations => [
                Permission::ApproveListing,
                Permission::PublishListing,
                Permission::OpenPrivateDocuments,
                Permission::RevealPhone,
                Permission::ManageCrm,
                Permission::AdvanceCase,
                Permission::ApproveCommissions,
                Permission::ApproveBroadcasts,
                Permission::ExportCsv,
                Permission::ViewUsers,
                Permission::SuspendUser,
            ],
            self::Reviewer => [
                Permission::ApproveListing,
                Permission::OpenPrivateDocuments,
            ],
            self::Sales => [
                Permission::ManageCrm,
                Permission::AdvanceCase,
                Permission::ViewUsers,
                Permission::ViewCommissions,
            ],
            self::Finance => [
                Permission::EditCommissions,
                Permission::AdvanceFinanceStages,
                Permission::ExportCsv,
            ],
        };
    }
}
