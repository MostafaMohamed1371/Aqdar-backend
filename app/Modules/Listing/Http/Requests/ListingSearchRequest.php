<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Modules\Listing\Services\ListingSearchService;

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
            'property_type' => ['sometimes', 'string', 'max:64'],
            'category_id' => ['sometimes', 'integer'],
            'featured' => ['sometimes', 'boolean'],
            'bbox' => ['sometimes', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (ListingSearchService::parseBoundingBox((string) $value) === null) {
                    $fail(__('aqdar.listings.bbox_invalid'));
                }
            }],
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

        if ($this->exists('featured')) {
            $featured = filter_var($this->input('featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $this->merge(['featured' => $featured ?? $this->input('featured')]);
        }

        if ($this->exists('bbox')) {
            $this->merge(['bbox' => trim((string) $this->input('bbox'))]);
        }
    }
}
