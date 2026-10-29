<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class SuspendUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suspend_user') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['reason'];
    }
}
