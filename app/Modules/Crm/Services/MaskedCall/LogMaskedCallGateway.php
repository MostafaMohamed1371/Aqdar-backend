<?php

namespace App\Modules\Crm\Services\MaskedCall;

use App\Modules\Crm\Models\TransferCase;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default driver until a telephony provider is configured: records the request and returns no proxy number.
 */
class LogMaskedCallGateway implements MaskedCallGateway
{
    public function connect(TransferCase $case, User $caller): array
    {
        Log::info('masked_call.requested', ['case_id' => $case->id, 'caller_user_id' => $caller->id]);

        return ['provider_session_id' => null, 'proxy_number' => null];
    }
}
