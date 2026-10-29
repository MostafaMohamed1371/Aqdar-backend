<?php

namespace App\Modules\Crm\Http\Requests;

use App\Core\Enums\BuyerIntent;
use App\Core\Enums\ContactMethod;
use App\Core\Enums\SegmentPreference;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreBuyerRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::enum(BuyerIntent::class)],
            'segment_preference' => ['nullable', Rule::enum(SegmentPreference::class)],
            'unit_type_id' => ['nullable', 'integer', 'exists:unit_types,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'developer_id' => ['nullable', 'integer', 'exists:developers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'area_sqm_min' => ['nullable', 'numeric', 'min:0'],
            'area_sqm_max' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:5'],
            'finishing' => ['nullable', 'string', 'max:64'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'duration_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'timeline' => ['nullable', 'string', 'max:64'],
            'priorities' => ['nullable', 'string', 'max:2000'],
            'contact_method' => ['required', Rule::enum(ContactMethod::class)],
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
        return array_merge(parent::fieldsToTrim(), ['priorities', 'timeline', 'finishing']);
    }
}
