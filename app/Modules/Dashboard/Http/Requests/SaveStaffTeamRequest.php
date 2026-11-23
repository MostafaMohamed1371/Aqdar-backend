<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Modules\Staff\Models\StaffTeam;
use Illuminate\Validation\Rule;

class SaveStaffTeamRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_staff') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $team = $this->route('staffTeam');

        return [
            'slug' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('staff_teams', 'slug')->ignore($team instanceof StaffTeam ? $team->id : null),
            ],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'sales_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 \-()]{5,30}$/'],
            'sales_hours_ar' => ['nullable', 'string', 'max:120'],
            'sales_hours_en' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->exists('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['slug', 'name_ar', 'name_en', 'sales_phone', 'sales_hours_ar', 'sales_hours_en'];
    }
}
