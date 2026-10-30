<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class CompareListingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:2', 'max:3'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /** @return list<int> */
    public function listingIds(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('ids'))) {
            $this->merge(['ids' => array_values(array_filter(explode(',', $this->input('ids')), 'strlen'))]);
        }
    }
}
