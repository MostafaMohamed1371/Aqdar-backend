<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class SaveListingStepRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route('step')) {
            'category' => [
                'category_id' => ['required', 'integer', 'exists:categories,id'],
                'distressed_child' => ['sometimes', 'nullable', 'in:a,b'],
            ],
            'specs' => [
                'governorate_id' => ['sometimes', 'integer', 'exists:governorates,id'],
                'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
                'developer_id' => ['sometimes', 'nullable', 'integer', 'exists:developers,id'],
                'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
                'unit_type_id' => ['sometimes', 'integer', 'exists:unit_types,id'],
                'title_ar' => ['sometimes', 'string', 'min:3', 'max:255'],
                'title_en' => ['sometimes', 'nullable', 'string', 'max:255'],
                'description_ar' => ['sometimes', 'string', 'min:10', 'max:5000'],
                'description_en' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'area_sqm' => ['sometimes', 'numeric', 'gt:0', 'max:100000'],
                'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:30'],
                'bathrooms' => ['sometimes', 'integer', 'min:0', 'max:30'],
                'floor' => ['sometimes', 'nullable', 'integer', 'min:-5', 'max:200'],
                'finishing' => ['sometimes', 'nullable', 'string', 'max:64'],
                'furnished' => ['sometimes', 'nullable', 'in:unfurnished,furnished'],
                'handover_date' => ['sometimes', 'nullable', 'date'],
                'neighborhood' => ['sometimes', 'nullable', 'string', 'max:128'],
                'phase' => ['sometimes', 'nullable', 'string', 'max:64'],
                'unit_code' => ['sometimes', 'nullable', 'string', 'max:64'],
                'parking' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
                'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
                'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
                'amenity_ids' => ['sometimes', 'array'],
                'amenity_ids.*' => ['integer', 'exists:amenities,id'],
                'tag_ids' => ['sometimes', 'array'],
                'tag_ids.*' => ['integer', 'exists:tags,id'],
                'custom_attributes' => ['sometimes', 'nullable', 'array', 'max:40'],
            ],
            'financials' => [
                'declared_contract_price' => ['sometimes', 'numeric', 'min:0'],
                'declared_contract_date' => ['sometimes', 'nullable', 'date'],
                'declared_total_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'declared_remaining' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'declared_overdue_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600'],
                'declared_overdue_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'declared_next_due_date' => ['sometimes', 'nullable', 'date'],
                'declared_installment_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'declared_remaining_installments' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600'],
                'declared_payment_system' => ['sometimes', 'nullable', 'in:cash,installment,mix'],
                'declared_seller_recovery_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'declared_asking_price' => ['sometimes', 'numeric', 'min:0'],
                'declared_cancellation_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'declared_assignment_restrictions' => ['sometimes', 'nullable', 'string', 'max:300'],
                'offer_expires_at' => ['sometimes', 'nullable', 'date'],
                'installment_items' => ['sometimes', 'array', 'max:360'],
                'installment_items.*.sequence' => ['sometimes', 'integer', 'min:1'],
                'installment_items.*.due_date' => ['sometimes', 'nullable', 'date'],
                'installment_items.*.amount' => ['required_with:installment_items', 'numeric', 'min:0'],
                'installment_items.*.note' => ['sometimes', 'nullable', 'string', 'max:255'],
                'installment_items.*.paid' => ['sometimes', 'boolean'],
                'installment_items.*.evidence_document_id' => ['sometimes', 'nullable', 'integer', 'exists:listing_documents,id'],
            ],
            'media', 'documents', 'review' => [
                'media_meta' => ['sometimes', 'array'],
                'documents_meta' => ['sometimes', 'array'],
                'acknowledged' => ['sometimes', 'boolean'],
            ],
            default => [],
        };
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['title_ar', 'title_en', 'description_ar', 'description_en', 'finishing', 'neighborhood', 'phase', 'unit_code', 'declared_assignment_restrictions'];
    }
}
