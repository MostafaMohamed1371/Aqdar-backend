<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ManualEstimateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contract_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'total_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'remaining' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cancellation_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'asking_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'listing_id' => ['sometimes', 'nullable', 'integer', 'exists:listings,id'],
            'area_id' => ['sometimes', 'nullable', 'integer', 'exists:areas,id'],
            'unit_type_id' => ['sometimes', 'nullable', 'integer', 'exists:unit_types,id'],
        ];
    }
}
