<?php

namespace App\Modules\Category\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category');

        return [
            'name' => ValidationRules::name(required: false),
            'name_ar' => ValidationRules::name(required: false),
            'name_en' => ValidationRules::name(required: false),
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'kind' => ['sometimes', 'string', 'max:32'],
            'segment' => ['nullable', 'string', 'max:32'],
            'distressed_child' => ['nullable', 'string', 'max:8'],
            'help_ar' => ['nullable', 'string', 'max:2000'],
            'help_en' => ['nullable', 'string', 'max:2000'],
            'commission_policy_code' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->exists('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->exists('commission_policy_code') && trim((string) $this->input('commission_policy_code')) === '') {
            $this->merge(['commission_policy_code' => null]);
        }
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name', 'name_ar', 'name_en', 'slug', 'help_ar', 'help_en', 'commission_policy_code'];
    }
}
