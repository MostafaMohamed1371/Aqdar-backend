<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class CloseAccountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
