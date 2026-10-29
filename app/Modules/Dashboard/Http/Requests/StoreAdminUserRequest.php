<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\AccountType;
use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Identity\EgyptianPhone;
use App\Core\Validation\ValidationRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdminUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suspend_user') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ValidationRules::name(),
            'email' => ValidationRules::email(required: false, unique: ValidationRules::uniqueEmail($this->ignoredUserId())),
            'phone' => ['nullable', 'string', 'max:20', ValidationRules::uniquePhone($this->ignoredUserId())],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'is_buyer' => ['sometimes', 'boolean'],
            'is_seller' => ['sometimes', 'boolean'],
        ];
    }

    protected function ignoredUserId(): ?int
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $phone = $this->filled('phone') ? (string) $this->input('phone') : null;

        if (is_string($phone) && $phone !== '' && EgyptianPhone::isValid($phone)) {
            $phone = EgyptianPhone::normalize($phone);
        }

        $this->merge([
            'email' => $this->filled('email') ? $this->input('email') : null,
            'phone' => $phone !== '' ? $phone : null,
            'is_buyer' => $this->has('is_buyer') ? $this->boolean('is_buyer') : true,
            'is_seller' => $this->boolean('is_seller'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = $this->input('phone');

            if (is_string($phone) && $phone !== '' && ! EgyptianPhone::isValid($phone)) {
                $validator->errors()->add('phone', __('aqdar.validation.phone'));
            }

            if (! $this->filled('email') && ! $this->filled('phone')) {
                $validator->errors()->add('phone', __('aqdar.users.email_or_phone'));
            }
        });
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'email', 'phone'];
    }
}
