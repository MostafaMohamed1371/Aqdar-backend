<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\CommissionBasis;
use App\Core\Enums\CommissionPayer;
use App\Core\Enums\CommissionStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_commissions') ?? false;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', 'exists:cases,id'],
            'payer_party' => ['required', Rule::enum(CommissionPayer::class)],
            'basis' => ['required', Rule::enum(CommissionBasis::class)],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:basis,percent'],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_if:basis,fixed'],
            'sale_value' => ['nullable', 'numeric', 'min:0'],
            'invoice_reference' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::enum(CommissionStatus::class)],
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
