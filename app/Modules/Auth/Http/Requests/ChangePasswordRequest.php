<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;

class ChangePasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => $this->user()?->password !== null
                ? ['required', 'string', 'current_password:sanctum']
                : ['prohibited'],
            'password' => ValidationRules::password(confirmed: true),
        ];
    }
}
