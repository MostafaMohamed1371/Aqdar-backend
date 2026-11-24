<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Enums\AccountType;
use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;
use App\Modules\User\Support\ProfileName;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\pL\s\-\'\.]+$/u',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,filter',
                'max:255',
                ValidationRules::uniqueEmail($this->user()?->id),
            ],
            'is_seller' => ['sometimes', 'boolean'],
            'is_buyer' => ['sometimes', 'boolean'],
            'account_type' => ['sometimes', Rule::enum(AccountType::class)],
            'locale' => ['sometimes', 'in:'.implode(',', config('aqdar.locales', ['ar', 'en']))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $current = $this->user()?->email;
            $requested = $this->input('email');

            if ($current !== null && is_string($requested) && strcasecmp(trim($requested), $current) !== 0) {
                $validator->errors()->add('email', __('aqdar.auth.email_locked'));
            }

            if (! $this->filled('name')) {
                return;
            }

            $name = is_string($this->input('name')) ? $this->input('name') : '';

            if (ProfileName::isPlaceholder($name) || ProfileName::isTooShort($name)) {
                $validator->errors()->add('name', __('aqdar.auth.name_invalid'));
            }
        });
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'email'];
    }
}
