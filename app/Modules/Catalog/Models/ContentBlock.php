<?php

namespace App\Modules\Catalog\Models;

use App\Core\Enums\ContentBlockType;
use App\Modules\Catalog\Database\Factories\ContentBlockFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'key',
    'title_ar',
    'title_en',
    'body_ar',
    'body_en',
    'image_path',
    'link_url',
    'is_active',
    'sort_order',
])]
class ContentBlock extends Model
{
    /** @use HasFactory<ContentBlockFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): ContentBlockFactory
    {
        return ContentBlockFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => ContentBlockType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
