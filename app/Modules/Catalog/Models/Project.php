<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProjectFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['developer_id', 'slug', 'name_ar', 'name_en', 'is_other', 'is_active', 'sort_order'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_other' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }
}
