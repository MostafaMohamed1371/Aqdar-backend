<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BulkModerateListingsRequest extends BaseFormRequest
{
    public const ACTIONS = ['approve', 'request_changes', 'reject'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
