<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class VerifyTwoFactorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'size:64'],
            'code' => ['required', 'string', 'size:'.max(4, (int) config('aqdar.otp.length', 4))],
        ];
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['challenge', 'code'];
    }
}
