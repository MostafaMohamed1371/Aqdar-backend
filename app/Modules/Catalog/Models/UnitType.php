<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\UnitTypeFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class UnitType extends Model
{
    /** @use HasFactory<UnitTypeFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): UnitTypeFactory
    {
        return UnitTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
