<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;

class ResetPasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ValidationRules::text(min: 1, max: 255),
            'email' => ValidationRules::loginEmail(),
            'password' => ValidationRules::password(confirmed: true),
        ];
    }
}
