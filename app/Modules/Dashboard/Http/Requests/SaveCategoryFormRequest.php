<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\FormFieldType;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCategoryFormRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:40'],
            'fields.*.key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label_ar' => ['nullable', 'string', 'max:191'],
            'fields.*.label_en' => ['nullable', 'string', 'max:191'],
            'fields.*.type' => ['nullable', Rule::enum(FormFieldType::class)],
            'fields.*.options' => ['nullable', 'string', 'max:1000'],
            'fields.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seenKeys = [];

            foreach ((array) $this->input('fields', []) as $index => $row) {
                $row = (array) $row;
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $key = (string) ($row['key'] ?? '');
                if ($key === '') {
                    $validator->errors()->add("fields.$index.key", __('aqdar.forms.key_required'));
                } elseif (isset($seenKeys[$key])) {
                    $validator->errors()->add("fields.$index.key", __('aqdar.forms.key_duplicate'));
                }
                $seenKeys[$key] = true;

                if (blank($row['label_ar'] ?? null)) {
                    $validator->errors()->add("fields.$index.label_ar", __('aqdar.forms.label_required'));
                }

                if (FormFieldType::tryFrom((string) ($row['type'] ?? '')) === null) {
                    $validator->errors()->add("fields.$index.type", __('aqdar.forms.type_required'));
                }

                if (($row['type'] ?? null) === FormFieldType::Select->value && $this->parseOptions($row) === []) {
                    $validator->errors()->add("fields.$index.options", __('aqdar.forms.options_required'));
                }
            }
        }];
    }

    /**
     * @return list<array{field_key: string, label_ar: string, label_en: ?string, type: FormFieldType, options: ?list<string>, is_required: bool}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ((array) $this->input('fields', []) as $row) {
            $row = (array) $row;
            if ($this->isBlankRow($row)) {
                continue;
            }

            $type = FormFieldType::from((string) $row['type']);
            $rows[] = [
                'field_key' => (string) $row['key'],
                'label_ar' => trim((string) $row['label_ar']),
                'label_en' => filled($row['label_en'] ?? null) ? trim((string) $row['label_en']) : null,
                'type' => $type,
                'options' => $type === FormFieldType::Select ? $this->parseOptions($row) : null,
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function isBlankRow(array $row): bool
    {
        return blank($row['key'] ?? null) && blank($row['label_ar'] ?? null) && blank($row['label_en'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function parseOptions(array $row): array
    {
        return collect(explode(',', (string) ($row['options'] ?? '')))
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
