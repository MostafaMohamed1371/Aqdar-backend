<?php

namespace App\Modules\Catalog\Models;

use App\Core\Support\PublicMediaUrl;
use App\Modules\Catalog\Database\Factories\DeveloperFactory;
use App\Modules\Catalog\Support\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_ar', 'name_en', 'commercial_register', 'logo_path', 'registered_on', 'is_other', 'is_verified', 'is_active', 'sort_order'])]
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
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'registered_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function logoUrl(): ?string
    {
        return PublicMediaUrl::for($this->logo_path);
    }

    /** approved | pending | suspended — matches the ADM-05 status badge. */
    public function registryStatus(): string
    {
        return match (true) {
            ! $this->is_active => 'suspended',
            ! $this->is_verified => 'pending',
            default => 'approved',
        };
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('sort_order');
    }
}
