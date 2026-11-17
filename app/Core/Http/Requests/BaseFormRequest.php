<?php

namespace App\Core\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class BaseFormRequest extends FormRequest
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('structer.name'),
            'email' => __('structer.email'),
            'password' => __('structer.password'),
            'password_confirmation' => __('structer.confirm_password'),
            'remember' => __('structer.remember_me'),
            'token' => __('structer.reset_password'),
            'phone' => __('aqdar.users.phone'),
            'code' => __('aqdar.auth.otp_code'),
            'device_uuid' => __('aqdar.validation.device_uuid'),
            'platform' => __('aqdar.validation.platform'),
            'account_type' => __('aqdar.users.type'),
            'reason' => __('aqdar.users.reason'),
            'consent' => __('aqdar.auth.consent'),
            'consent_privacy' => __('aqdar.auth.consent_privacy'),
            'consent_terms' => __('aqdar.auth.consent_terms'),
            'locale' => __('aqdar.auth.locale'),
            'contact_method' => __('aqdar.requests.contact_method'),
            'preferred_time' => __('aqdar.requests.preferred_time'),
            'listing_id' => __('aqdar.crm.listing_id'),
            'message' => __('aqdar.requests.inquiry_message'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('structer.validation.name.required'),
            'name.string' => __('structer.validation.name.string'),
            'name.min' => __('structer.validation.name.min'),
            'name.max' => __('structer.validation.name.max'),
            'name.regex' => __('structer.validation.name.regex'),

            'email.required' => __('structer.validation.email.required'),
            'email.string' => __('structer.validation.email.string'),
            'email.email' => __('structer.validation.email.email'),
            'email.max' => __('structer.validation.email.max'),
            'email.unique' => __('structer.validation.email.unique'),

            'password.required' => __('structer.validation.password.required'),
            'password.string' => __('structer.validation.password.string'),
            'password.min' => __('structer.validation.password.min'),
            'password.max' => __('structer.validation.password.max'),
            'password.confirmed' => __('structer.validation.password.confirmed'),
            'password.letters' => __('structer.validation.password.letters'),
            'password.numbers' => __('structer.validation.password.numbers'),

            'password_confirmation.required' => __('structer.validation.password_confirmation.required'),

            'remember.boolean' => __('structer.validation.remember.boolean'),

            'token.required' => __('structer.validation.token.required'),
            'token.string' => __('structer.validation.token.string'),

            'phone.required' => __('aqdar.validation.phone_required'),
            'phone.string' => __('aqdar.validation.phone'),
            'code.required' => __('aqdar.validation.code_required'),
            'code.size' => __('aqdar.validation.code_size'),
            'device_uuid.required' => __('aqdar.validation.device_uuid'),
            'platform.in' => __('aqdar.validation.platform'),
            'account_type.Illuminate\Validation\Rules\Enum' => __('aqdar.validation.account_type'),
            'is_seller.boolean' => __('aqdar.validation.boolean'),
            'is_buyer.boolean' => __('aqdar.validation.boolean'),
            'reason.required' => __('aqdar.users.reason_required'),
            'reason.min' => __('aqdar.validation.reason'),
            'consent.required' => __('aqdar.auth.consent_required'),
            'consent.accepted' => __('aqdar.auth.consent_required'),
            'consent_privacy.required' => __('aqdar.auth.consent_required'),
            'consent_privacy.accepted' => __('aqdar.auth.consent_required'),
            'consent_terms.required' => __('aqdar.auth.consent_required'),
            'consent_terms.accepted' => __('aqdar.auth.consent_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach ($this->fieldsToTrim() as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merged[$field] = trim($value);
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'email'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
