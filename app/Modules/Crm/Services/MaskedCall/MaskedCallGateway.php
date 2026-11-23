<?php

namespace App\Modules\Crm\Services\MaskedCall;

use App\Modules\Crm\Models\TransferCase;
use App\Modules\User\Models\User;

interface MaskedCallGateway
{
    /**
     * Opens a call between the caller and the other party of the case without exposing either phone number.
     *
     * @return array{provider_session_id: ?string, proxy_number: ?string}
     */
    public function connect(TransferCase $case, User $caller): array;
}
