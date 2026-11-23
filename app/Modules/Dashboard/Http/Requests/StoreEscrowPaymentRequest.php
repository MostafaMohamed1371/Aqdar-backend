<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\EscrowMethod;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreEscrowPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'method' => ['required', Rule::enum(EscrowMethod::class)],
            'reference' => ['nullable', 'string', 'max:128'],
            'received_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
