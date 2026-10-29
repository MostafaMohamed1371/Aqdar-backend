<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ScheduleBroadcastRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_catalog') ?? false;
    }

    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'body_ar' => ['required', 'string', 'max:500'],
            'body_en' => ['nullable', 'string', 'max:500'],
            'audience' => ['nullable', 'in:marketing_opted_in'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
