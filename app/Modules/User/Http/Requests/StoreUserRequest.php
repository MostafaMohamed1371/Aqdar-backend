<?php

namespace App\Modules\User\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;

class StoreUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ValidationRules::name(),
            'email' => ValidationRules::email(unique: ValidationRules::uniqueEmail()),
            'password' => ValidationRules::password(),
        ];
    }
}
