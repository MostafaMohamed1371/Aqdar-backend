<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class StaffTwoFactorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }
}
