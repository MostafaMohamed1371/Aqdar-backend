<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_catalog') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform_name' => ['required', 'string', 'max:80'],
            'support_email' => ['required', 'email', 'max:120'],
            'sales_name' => ['required', 'string', 'max:80'],
            'sales_name_en' => ['required', 'string', 'max:80'],
            'sales_phone' => ['nullable', 'string', 'max:32'],
            'sales_hours' => ['required', 'string', 'max:120'],
            'sales_hours_en' => ['required', 'string', 'max:120'],
            'lead_assignment_rule' => ['required', Rule::in(['manual', 'round_robin'])],
            'maintenance_mode' => ['required', Rule::in(['0', '1'])],
            'feature_smart_notifications' => ['required', Rule::in(['0', '1'])],
            'commission_default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'audit_retention_months' => ['required', 'integer', 'min:1', 'max:120'],
            'notification_quiet_hours' => ['nullable', 'string', 'max:20', 'regex:/^(\\d{2}:\\d{2}-\\d{2}:\\d{2})?$/'],
            'default_locale' => ['required', Rule::in(['ar', 'en'])],
        ];
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['platform_name', 'support_email', 'sales_name', 'sales_name_en', 'sales_phone', 'sales_hours', 'sales_hours_en', 'notification_quiet_hours'];
    }
}
