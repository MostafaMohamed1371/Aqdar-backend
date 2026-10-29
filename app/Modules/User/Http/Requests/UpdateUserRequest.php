<?php

namespace App\Modules\User\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;

class UpdateUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ValidationRules::name(required: false),
            'email' => ValidationRules::email(
                required: false,
                unique: ValidationRules::uniqueEmail(is_numeric($userId) ? (int) $userId : null),
            ),
            'password' => ValidationRules::password(required: false),
        ];
    }
}
