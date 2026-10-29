<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\CommissionBasis;
use App\Core\Enums\CommissionPayer;
use App\Core\Enums\CommissionStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_commissions') ?? false;
    }

    public function rules(): array
    {
        return [
            'payer_party' => ['sometimes', Rule::enum(CommissionPayer::class)],
            'basis' => ['sometimes', Rule::enum(CommissionBasis::class)],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'sale_value' => ['nullable', 'numeric', 'min:0'],
            'invoice_reference' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::enum(CommissionStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
