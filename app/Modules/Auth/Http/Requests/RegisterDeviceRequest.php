<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'string', Rule::in(['ios', 'android', 'web', 'huawei'])],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['device_uuid', 'platform', 'push_token'];
    }
}
