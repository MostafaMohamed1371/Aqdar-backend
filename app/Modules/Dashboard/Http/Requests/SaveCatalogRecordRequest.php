<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\ContentBlockType;
use App\Core\Http\Requests\BaseFormRequest;
use App\Core\Validation\ValidationRules;
use Illuminate\Validation\Rule;

class SaveCatalogRecordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_catalog') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ar' => ValidationRules::name(required: false),
            'name_en' => ValidationRules::name(required: false),
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['sometimes', 'boolean'],
            'is_other' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'developer_id' => ['nullable', 'integer', 'exists:developers,id'],
            'type' => ['sometimes', Rule::enum(ContentBlockType::class)],
            'key' => ['nullable', 'string', 'max:64'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_ar' => ['nullable', 'string', 'max:5000'],
            'body_en' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp'],
            'logo' => ['nullable', 'image', 'max:1024', 'mimes:jpeg,jpg,png,webp'],
            'commercial_register' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z-]+$/'],
            'registered_on' => ['nullable', 'date', 'before_or_equal:today'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 \-()]{5,30}$/'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'is_verified' => ['sometimes', 'boolean'],
            'code' => [
                'nullable', 'string', 'min:2', 'max:8', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('governorates', 'code')->ignore($this->route('governorate')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $merged = [];

        if ($this->exists('governorate_id')) {
            $merged['governorate_id'] = $this->filled('governorate_id') ? $this->input('governorate_id') : null;
        }

        if ($this->exists('developer_id')) {
            $merged['developer_id'] = $this->filled('developer_id') ? $this->input('developer_id') : null;
        }

        if ($this->exists('is_active')) {
            $merged['is_active'] = $this->boolean('is_active');
        }

        if ($this->exists('is_other')) {
            $merged['is_other'] = $this->boolean('is_other');
        }

        if ($this->exists('is_verified')) {
            $merged['is_verified'] = $this->boolean('is_verified');
        }

        if ($this->exists('code')) {
            $merged['code'] = $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null;
        }

        $this->merge($merged);
    }

    /** @return list<string> */
    protected function fieldsToTrim(): array
    {
        return ['name_ar', 'name_en', 'slug', 'key', 'title_ar', 'title_en', 'body_ar', 'body_en', 'link_url', 'commercial_register', 'contact_name', 'contact_phone', 'contact_email', 'website', 'internal_notes'];
    }
}
