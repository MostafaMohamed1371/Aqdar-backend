<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class UpdateNotificationTemplateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_catalog') ?? false;
    }

    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:120'],
            'title_en' => ['required', 'string', 'max:120'],
            'body_ar' => ['required', 'string', 'max:500'],
            'body_en' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
