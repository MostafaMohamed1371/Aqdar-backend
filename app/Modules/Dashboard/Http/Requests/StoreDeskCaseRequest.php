<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class StoreDeskCaseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', 'integer', 'exists:users,id'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:staff_users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
