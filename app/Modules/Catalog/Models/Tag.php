<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\TagFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
