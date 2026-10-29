<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Validation\ValidationRules;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends StoreStaffRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $staffId = $this->staffId();

        return [
            'name' => ValidationRules::name(),
            'email' => [
                'required',
                'string',
                'email:rfc,filter',
                'max:255',
                Rule::unique('staff_users', 'email')->ignore($staffId),
            ],
            'password' => $this->filled('password')
                ? ValidationRules::password()
                : ['nullable'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    private function staffId(): ?int
    {
        $staff = $this->route('staffUser');

        return $staff instanceof StaffUser ? $staff->id : null;
    }
}
