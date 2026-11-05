<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Identity\EgyptianPhone;
use Illuminate\Validation\Validator;

class VerifyOtpRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:'.max(4, (int) config('aqdar.otp.length', 4))],
            'consent' => ['sometimes', 'accepted'],
            'consent_privacy' => ['sometimes', 'accepted'],
            'consent_terms' => ['sometimes', 'accepted'],
            'locale' => ['sometimes', 'in:'.implode(',', config('aqdar.locales', ['ar', 'en']))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = $this->input('phone');

            if (is_string($phone) && $phone !== '' && ! EgyptianPhone::isValid($phone)) {
                $validator->errors()->add('phone', __('aqdar.validation.phone'));
            }
        });
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['phone', 'code'];
    }
}
