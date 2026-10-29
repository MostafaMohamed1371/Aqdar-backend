<?php

namespace App\Core\Enums;

enum CaseDocumentKind: string
{
    case DeveloperApproval = 'developer_approval';
    case BuyerRequested = 'buyer_requested';
    case Other = 'other';
}
