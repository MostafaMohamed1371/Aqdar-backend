<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SettleEscrowPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['released', 'refunded'])],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
