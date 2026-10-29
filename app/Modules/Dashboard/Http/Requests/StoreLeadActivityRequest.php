<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\ContactedParty;
use App\Core\Enums\LeadActivityType;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreLeadActivityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LeadActivityType::class)],
            'contacted_party' => ['nullable', Rule::enum(ContactedParty::class)],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'seller_visible_amount' => ['nullable', 'numeric', 'min:0'],
            'next_action' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function fieldsToTrim(): array
    {
        return array_merge(parent::fieldsToTrim(), ['body', 'next_action']);
    }
}
