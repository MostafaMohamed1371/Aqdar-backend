<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;

class RegisterRequest extends BaseFormRequest
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
            'password' => ValidationRules::password(confirmed: true),
            'consent' => ['sometimes', 'accepted'],
            'consent_privacy' => ['required', 'accepted'],
            'consent_terms' => ['required', 'accepted'],
            'locale' => ['sometimes', 'in:'.implode(',', config('aqdar.locales', ['ar', 'en']))],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (in_array($this->input('consent'), [true, 1, '1', 'true', 'on', 'yes'], true)) {
            $this->merge([
                'consent_privacy' => true,
                'consent_terms' => true,
            ]);
        }
    }
}
