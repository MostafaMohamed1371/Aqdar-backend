<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\GovernorateFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class Governorate extends Model
{
    /** @use HasFactory<GovernorateFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): GovernorateFactory
    {
        return GovernorateFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('sort_order');
    }
}
