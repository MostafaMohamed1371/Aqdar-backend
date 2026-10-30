<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_staff') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ValidationRules::name(),
            'email' => [
                'required',
                'string',
                'email:rfc,filter',
                'max:255',
                Rule::unique('staff_users', 'email'),
            ],
            'password' => ValidationRules::password(),
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'branch' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'email'];
    }
}
