<?php

namespace App\Modules\Category\Models;

use App\Core\Enums\CategoryKind;
use App\Core\Enums\DistressedChild;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Support\HasLocalizedName;
use App\Modules\Category\Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'name_ar',
    'name_en',
    'slug',
    'kind',
    'segment',
    'distressed_child',
    'help_ar',
    'help_en',
    'commission_policy_code',
    'parent_id',
    'is_active',
    'sort_order',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasLocalizedName;

    public const PROTECTED_SLUGS = [
        'distressed',
        'distressed-a',
        'distressed-b',
        'non-distressed',
    ];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'segment' => MarketSegment::class,
            'distressed_child' => DistressedChild::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function isProtected(): bool
    {
        return in_array($this->slug, self::PROTECTED_SLUGS, true);
    }

    public function isDistressedRoot(): bool
    {
        return $this->kind === CategoryKind::Segment
            && $this->segment === MarketSegment::Distressed
            && $this->parent_id === null;
    }
}
