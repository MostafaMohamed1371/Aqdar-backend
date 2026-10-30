<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class VerifyListingFinancialsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'verified_contract_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_total_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_remaining' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_overdue_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'verified_overdue_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_next_due_date' => ['sometimes', 'nullable', 'date'],
            'verified_installment_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_remaining_installments' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'verified_seller_recovery_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_asking_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'verified_cancellation_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
