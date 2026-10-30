<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ApproveCommissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_commissions') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }
}
