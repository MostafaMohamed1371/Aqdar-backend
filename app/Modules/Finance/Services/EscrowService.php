<?php

namespace App\Modules\Finance\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\EscrowStatus;
use App\Core\Enums\Permission;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Finance\Models\EscrowPayment;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Validation\ValidationException;

class EscrowService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function record(TransferCase $case, StaffUser $actor, array $data): EscrowPayment
    {
        abort_unless($actor->hasPermission(Permission::EditCommissions), 403, __('aqdar.http.forbidden'));
        if ($case->status?->isClosed()) {
            throw ValidationException::withMessages(['status' => [__('aqdar.escrow.case_closed')]]);
        }

        $payment = $case->escrowPayments()->create([
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'status' => EscrowStatus::Held,
            'received_at' => $data['received_at'] ?? now(),
            'recorded_by_staff_id' => $actor->id,
            'note' => $data['note'] ?? null,
        ]);

        $this->audit->write('escrow.recorded', $actor, $payment, [
            'case_id' => $case->id,
            'amount' => (string) $payment->amount,
            'method' => $payment->method?->value,
        ]);
        DeskRealtime::push('cases', 'escrow', (int) $case->id, meta: ['status' => EscrowStatus::Held->value]);

        return $payment;
    }

    public function settle(EscrowPayment $payment, StaffUser $actor, EscrowStatus $to, string $note): EscrowPayment
    {
        abort_unless($actor->hasPermission(Permission::ApproveCommissions), 403, __('aqdar.http.forbidden'));
        abort_unless(in_array($to, [EscrowStatus::Released, EscrowStatus::Refunded], true), 422);

        if ($payment->status?->isSettled()) {
            throw ValidationException::withMessages(['status' => [__('aqdar.escrow.already_settled')]]);
        }

        $payment->forceFill([
            'status' => $to,
            'settled_at' => now(),
            'settled_by_staff_id' => $actor->id,
            'note' => $note,
        ])->save();

        $this->audit->write('escrow.'.$to->value, $actor, $payment, [
            'case_id' => $payment->case_id,
            'amount' => (string) $payment->amount,
        ]);
        DeskRealtime::push('cases', 'escrow', (int) $payment->case_id, meta: ['status' => $to->value]);

        return $payment->fresh() ?? $payment;
    }
}
