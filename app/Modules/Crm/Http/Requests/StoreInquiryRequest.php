<?php

namespace App\Modules\Crm\Http\Requests;

use App\Core\Enums\ContactMethod;
use App\Core\Enums\PreferredTime;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:2000'],
            'inquiry_type' => ['nullable', 'string', 'max:32'],
            'contact_method' => ['required', Rule::enum(ContactMethod::class)],
            'preferred_time' => ['nullable', Rule::enum(PreferredTime::class)],
            'consent' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->exists('consent')) {
            $this->merge(['consent' => $this->boolean('consent')]);
        }
    }

    protected function fieldsToTrim(): array
    {
        return array_merge(parent::fieldsToTrim(), ['message']);
    }
}
