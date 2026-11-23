<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\FormFieldType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Database\Factories\ListingFormFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id',
    'form_version',
    'field_key',
    'label_ar',
    'label_en',
    'type',
    'options',
    'is_required',
    'sort_order',
])]
class ListingFormField extends Model
{
    /** @use HasFactory<ListingFormFieldFactory> */
    use HasFactory;

    protected static function newFactory(): ListingFormFieldFactory
    {
        return ListingFormFieldFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'form_version' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function localizedLabel(): string
    {
        return app()->getLocale() === 'ar'
            ? $this->label_ar
            : ($this->label_en ?: $this->label_ar);
    }
}
