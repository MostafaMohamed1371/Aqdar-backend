<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\AmenityFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): AmenityFactory
    {
        return AmenityFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
