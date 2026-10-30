<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class UpdateSavedSearchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notify' => ['sometimes', 'boolean'],
            'filters' => ['sometimes', 'array'],
            'filters.q' => ['sometimes', 'nullable', 'string', 'min:1', 'max:100'],
            'filters.segment' => ['sometimes', 'nullable', 'in:distressed,non_distressed'],
            'filters.distressed_child' => ['sometimes', 'nullable', 'in:a,b'],
            'filters.governorate_id' => ['sometimes', 'nullable', 'integer'],
            'filters.area_id' => ['sometimes', 'nullable', 'integer'],
            'filters.developer_id' => ['sometimes', 'nullable', 'integer'],
            'filters.project_id' => ['sometimes', 'nullable', 'integer'],
            'filters.unit_type_id' => ['sometimes', 'nullable', 'integer'],
            'filters.tag_id' => ['sometimes', 'nullable', 'integer'],
            'filters.tag' => ['sometimes', 'nullable', 'string', 'max:64'],
            'filters.area_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.area_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.price_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.price_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.down_payment_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.down_payment_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.installment_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.installment_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
