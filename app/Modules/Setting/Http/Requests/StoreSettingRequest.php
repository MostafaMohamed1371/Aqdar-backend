<?php

namespace App\Modules\Setting\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreSettingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', Rule::unique('settings', 'key')],
            'value' => ['required', 'string', 'max:65535'],
            'group' => ['nullable', 'string', 'max:255'],
        ];
    }
}
