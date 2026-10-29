<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\DeveloperFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_ar', 'name_en', 'is_other', 'is_active', 'sort_order'])]
class Developer extends Model
{
    /** @use HasFactory<DeveloperFactory> */
    use HasFactory;
    use HasLocalizedName;

    protected static function newFactory(): DeveloperFactory
    {
        return DeveloperFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_other' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('sort_order');
    }
}
