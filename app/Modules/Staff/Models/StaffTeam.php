<?php

namespace App\Modules\Staff\Models;

use App\Modules\Staff\Database\Factories\StaffTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_ar', 'name_en', 'sales_phone', 'sales_hours_ar', 'sales_hours_en', 'is_active'])]
class StaffTeam extends Model
{
    /** @use HasFactory<StaffTeamFactory> */
    use HasFactory;

    protected $table = 'staff_teams';

    protected static function newFactory(): StaffTeamFactory
    {
        return StaffTeamFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StaffUser, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(StaffUser::class, 'team_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en'
            ? ($this->name_en ?: $this->name_ar)
            : ($this->name_ar ?: $this->name_en);
    }

    public function localizedHours(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $hours = $locale === 'en'
            ? ($this->sales_hours_en ?: $this->sales_hours_ar)
            : ($this->sales_hours_ar ?: $this->sales_hours_en);

        return $hours !== null && $hours !== '' ? $hours : null;
    }

    public function hasOwnSalesNumber(): bool
    {
        return $this->is_active && filled($this->sales_phone);
    }
}
