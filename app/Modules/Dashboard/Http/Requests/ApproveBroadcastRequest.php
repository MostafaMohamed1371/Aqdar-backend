<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ApproveBroadcastRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_broadcasts') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
