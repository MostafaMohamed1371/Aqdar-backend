<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\AreaFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['governorate_id', 'slug', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): AreaFactory
    {
        return AreaFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}
