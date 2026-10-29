<?php

namespace App\Modules\Setting\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settingId = $this->route('setting');

        return [
            'key' => ['sometimes', 'string', 'max:255', Rule::unique('settings', 'key')->ignore($settingId)],
            'value' => ['sometimes', 'string', 'max:65535'],
            'group' => ['nullable', 'string', 'max:255'],
        ];
    }
}
