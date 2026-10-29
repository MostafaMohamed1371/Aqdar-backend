<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\LeadPriority;
use App\Core\Enums\LeadSource;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreDeskLeadRequest extends BaseFormRequest
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
            'source' => ['required', Rule::enum(LeadSource::class)],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:staff_users,id'],
            'next_action' => ['nullable', 'string', 'max:120'],
        ];
    }
}
