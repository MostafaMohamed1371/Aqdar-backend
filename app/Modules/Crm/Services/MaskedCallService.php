<?php

namespace App\Modules\Crm\Services;

use App\Core\Enums\MaskedCallStatus;
use App\Modules\Crm\Models\MaskedCall;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\MaskedCall\MaskedCallGateway;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MaskedCallService
{
    public function __construct(
        private CaseService $cases,
        private MaskedCallGateway $gateway,
    ) {}

    public function start(TransferCase $case, User $caller): MaskedCall
    {
        $this->cases->showForParty($caller, $case);

        if ($case->status?->isClosed()) {
            throw ValidationException::withMessages(['case' => [__('aqdar.calls.case_closed')]]);
        }

        $limit = (int) config('aqdar.telephony.calls_per_hour', 5);
        $recent = MaskedCall::query()
            ->where('case_id', $case->id)
            ->where('caller_user_id', $caller->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        abort_if($recent >= $limit, 429, __('aqdar.calls.rate_limited'));

        $call = MaskedCall::query()->create([
            'case_id' => $case->id,
            'caller_user_id' => $caller->id,
            'provider' => (string) config('aqdar.telephony.driver', 'log'),
            'status' => MaskedCallStatus::PendingProvider,
        ]);

        try {
            $session = $this->gateway->connect($case, $caller);
        } catch (\Throwable $exception) {
            Log::warning('masked_call.failed', ['call_id' => $call->id, 'error' => $exception->getMessage()]);
            $call->forceFill(['status' => MaskedCallStatus::Failed])->save();

            return $call;
        }

        $call->forceFill([
            'status' => $session['proxy_number'] !== null ? MaskedCallStatus::Connected : MaskedCallStatus::PendingProvider,
            'provider_session_id' => $session['provider_session_id'],
            'proxy_number' => $session['proxy_number'],
        ])->save();

        return $call;
    }
}
