<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\LeadStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
