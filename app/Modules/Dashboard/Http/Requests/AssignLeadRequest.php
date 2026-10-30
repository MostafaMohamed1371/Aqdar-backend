<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class AssignLeadRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:staff_users,id'],
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }
}
