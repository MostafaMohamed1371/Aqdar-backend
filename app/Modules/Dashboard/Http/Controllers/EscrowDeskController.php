<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\EscrowStatus;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Dashboard\Http\Requests\SettleEscrowPaymentRequest;
use App\Modules\Dashboard\Http\Requests\StoreEscrowPaymentRequest;
use App\Modules\Finance\Models\EscrowPayment;
use App\Modules\Finance\Services\EscrowService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;

class EscrowDeskController extends BaseController
{
    public function __construct(private EscrowService $escrow) {}

    public function store(StoreEscrowPaymentRequest $request, TransferCase $transferCase): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->escrow->record($transferCase, $staff, $request->validated());

        return back()->with('status', __('aqdar.escrow.recorded'));
    }

    public function settle(SettleEscrowPaymentRequest $request, EscrowPayment $escrowPayment): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $data = $request->validated();
        $this->escrow->settle($escrowPayment, $staff, EscrowStatus::from($data['status']), $data['note']);

        return back()->with('status', __('aqdar.escrow.settled'));
    }
}
