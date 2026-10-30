<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class UpdateTwoFactorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'current_password' => $this->user()?->password !== null
                ? ['required', 'string', 'current_password:sanctum']
                : ['prohibited'],
        ];
    }
}
