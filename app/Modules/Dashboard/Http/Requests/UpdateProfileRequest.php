<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof StaffUser;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var StaffUser $staff */
        $staff = $this->user();

        return [
            'name' => ValidationRules::name(),
            'email' => [
                'required',
                'string',
                'email:rfc,filter',
                'max:255',
                Rule::unique('staff_users', 'email')->ignore($staff->id),
            ],
            'password' => $this->filled('password')
                ? ValidationRules::password(confirmed: true)
                : ['nullable'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp'],
            'remove_photo' => ['sometimes', 'boolean'],
            'role_id' => $this->canManageStaff()
                ? ['required', 'integer', Rule::exists('roles', 'id')]
                : ['nullable', 'integer'],
        ];
    }

    private function canManageStaff(): bool
    {
        $staff = $this->user();

        return $staff instanceof StaffUser && $staff->hasPermission(PermissionEnum::ManageStaff);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'photo' => __('aqdar.profile.photo'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'photo.uploaded' => __('aqdar.profile.photo_failed'),
            'photo.image' => __('aqdar.profile.photo_invalid'),
            'photo.mimes' => __('aqdar.profile.photo_invalid'),
        ]);
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'email'];
    }
}
