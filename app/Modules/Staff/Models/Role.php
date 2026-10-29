<?php

namespace App\Modules\Staff\Models;

use App\Core\Enums\StaffRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function staffUsers(): HasMany
    {
        return $this->hasMany(StaffUser::class);
    }

    public function staffRole(): ?StaffRole
    {
        return StaffRole::tryFrom($this->slug);
    }
}
