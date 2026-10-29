<?php

namespace App\Core\Enums;

enum CaseStage: string
{
    case NewClient = 'new_client';
    case QualifyBuyer = 'qualify_buyer';
    case ContactSeller = 'contact_seller';
    case DocumentsComplete = 'documents_complete';
    case AlignParties = 'align_parties';
    case DeveloperApproval = 'developer_approval';
    case ScheduleTransfer = 'schedule_transfer';
    case Completed = 'completed';

    public function clientLabel(): string
    {
        return match ($this) {
            self::NewClient => 'interest_received',
            self::QualifyBuyer => 'buyer_qualified',
            self::ContactSeller => 'seller_contacted',
            self::DocumentsComplete => 'documents_reviewed',
            self::AlignParties => 'agreement_in_progress',
            self::DeveloperApproval => 'awaiting_developer',
            self::ScheduleTransfer => 'transfer_scheduled',
            self::Completed => 'completed',
        };
    }

    /** @return list<self> */
    public static function pipeline(): array
    {
        return self::cases();
    }
}
