<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ListingSearchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
            'segment' => ['sometimes', 'in:distressed,non_distressed'],
            'distressed_child' => ['sometimes', 'in:a,b'],
            'governorate_id' => ['sometimes', 'integer'],
            'area_id' => ['sometimes', 'integer'],
            'developer_id' => ['sometimes', 'integer'],
            'project_id' => ['sometimes', 'integer'],
            'unit_type_id' => ['sometimes', 'integer'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'finishing' => ['sometimes', 'string', 'max:64'],
            'neighborhood' => ['sometimes', 'string', 'max:120'],
            'tag_id' => ['sometimes', 'integer'],
            'tag' => ['sometimes', 'string', 'max:64'],
            'area_min' => ['sometimes', 'numeric', 'min:0'],
            'area_max' => ['sometimes', 'numeric', 'min:0'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0'],
            'down_payment_min' => ['sometimes', 'numeric', 'min:0'],
            'down_payment_max' => ['sometimes', 'numeric', 'min:0'],
            'installment_min' => ['sometimes', 'numeric', 'min:0'],
            'installment_max' => ['sometimes', 'numeric', 'min:0'],
            'sort' => ['sometimes', 'in:newest,price_asc,price_desc,area_asc,area_desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->exists('q')) {
            $this->merge(['q' => trim((string) $this->input('q'))]);
        }
    }
}
