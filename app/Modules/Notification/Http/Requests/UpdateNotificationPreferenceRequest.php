<?php

namespace App\Modules\Notification\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class UpdateNotificationPreferenceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'in:'.implode(',', config('aqdar.locales', ['ar', 'en']))],
        ];
    }
}
