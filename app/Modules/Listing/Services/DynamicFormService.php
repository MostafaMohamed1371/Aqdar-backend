<?php

namespace App\Modules\Listing\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\FormFieldType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFormField;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Category-scoped listing fields (FR-051). Each publish creates a new immutable form version;
 * a listing keeps the version it was filled against, and changing its category clears those answers.
 */
class DynamicFormService
{
    private const MAX_TEXT_LENGTH = 500;

    public function __construct(private AuditLogger $audit) {}

    /** @return Collection<int, ListingFormField> */
    public function fieldsFor(Category $category): Collection
    {
        if ((int) $category->form_version === 0) {
            return new Collection;
        }

        return ListingFormField::query()
            ->where('category_id', $category->id)
            ->where('form_version', (int) $category->form_version)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{field_key: string, label_ar: string, label_en: ?string, type: FormFieldType, options: ?list<string>, is_required: bool}>  $rows
     */
    public function publish(Category $category, array $rows, StaffUser $actor): int
    {
        return DB::transaction(function () use ($category, $rows, $actor): int {
            $locked = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $version = (int) $locked->form_version + 1;

            foreach (array_values($rows) as $index => $row) {
                ListingFormField::query()->create([
                    'category_id' => $locked->id,
                    'form_version' => $version,
                    'field_key' => $row['field_key'],
                    'label_ar' => $row['label_ar'],
                    'label_en' => $row['label_en'],
                    'type' => $row['type'],
                    'options' => $row['options'],
                    'is_required' => $row['is_required'],
                    'sort_order' => $index,
                ]);
            }

            $locked->forceFill(['form_version' => $version])->save();
            $this->audit->write('category.form_published', $actor, $locked, [
                'form_version' => $version,
                'fields' => count($rows),
            ]);

            return $version;
        });
    }

    /**
     * Validates the custom answers sent for a listing and merges them with the stored ones.
     * Empty answers are dropped; required fields are only enforced on submit.
     *
     * @param  array<string, mixed>  $input
     * @return array{form_version: int, attributes: array<string, int|float|bool|string>}
     */
    public function attributesFor(Listing $listing, array $input): array
    {
        $category = $this->categoryOf($listing);
        $fields = $this->fieldsFor($category)->keyBy('field_key');

        $unknown = array_diff(array_keys($input), $fields->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['custom_attributes' => [__('aqdar.forms.unknown_field')]]);
        }

        $merged = array_merge($this->storedFor($listing, $category), $input);
        $attributes = [];

        foreach ($fields as $key => $field) {
            $value = $merged[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            $attributes[$key] = $this->cast($field, $value);
        }

        return ['form_version' => (int) $category->form_version, 'attributes' => $attributes];
    }

    public function assertComplete(Listing $listing): void
    {
        $category = $this->categoryOf($listing);
        $stored = $this->storedFor($listing, $category);
        $missing = [];

        foreach ($this->fieldsFor($category) as $field) {
            if ($field->is_required && ! isset($stored[$field->field_key])) {
                $missing['custom_attributes.'.$field->field_key] = [__('aqdar.forms.required', ['field' => $field->localizedLabel()])];
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    private function cast(ListingFormField $field, mixed $value): int|float|bool|string
    {
        if ($field->type === FormFieldType::Number) {
            if (! is_numeric($value)) {
                throw $this->invalid($field, __('aqdar.forms.invalid_number'));
            }

            return $value + 0;
        }

        if ($field->type === FormFieldType::Boolean) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                throw $this->invalid($field, __('aqdar.forms.invalid_boolean'));
            }

            return $bool;
        }

        if (! is_scalar($value)) {
            throw $this->invalid($field, __('aqdar.forms.invalid_text'));
        }

        $text = trim((string) $value);

        if ($field->type === FormFieldType::Select) {
            if (! in_array($text, (array) $field->options, true)) {
                throw $this->invalid($field, __('aqdar.forms.invalid_option'));
            }

            return $text;
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw $this->invalid($field, __('aqdar.forms.text_too_long'));
        }

        return $text;
    }

    private function invalid(ListingFormField $field, string $message): ValidationException
    {
        return ValidationException::withMessages(['custom_attributes.'.$field->field_key => [$message]]);
    }

    /** @return array<string, mixed> */
    private function storedFor(Listing $listing, Category $category): array
    {
        if ((int) $listing->form_version !== (int) $category->form_version) {
            return [];
        }

        return (array) ($listing->custom_attributes ?? []);
    }

    private function categoryOf(Listing $listing): Category
    {
        $listing->loadMissing('category');

        return $listing->category ?? throw ValidationException::withMessages([
            'category_id' => [__('aqdar.listings.category_required')],
        ]);
    }
}
