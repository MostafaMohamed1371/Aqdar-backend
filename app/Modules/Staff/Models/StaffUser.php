<?php

namespace App\Modules\Staff\Models;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Enums\StaffRole;
use App\Modules\Staff\Database\Factories\StaffUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'role_id', 'team_id', 'branch', 'avatar_path', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class StaffUser extends Authenticatable
{
    /** @use HasFactory<StaffUserFactory> */
    use HasFactory;

    protected $table = 'staff_users';

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(StaffTeam::class, 'team_id');
    }

    public function hasPermission(PermissionEnum|string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $slug = $permission instanceof PermissionEnum ? $permission->value : $permission;

        if ($this->role?->slug === StaffRole::SuperAdmin->value) {
            return true;
        }

        $this->loadMissing('role.permissions');

        return $this->role?->permissions->contains('slug', $slug) ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === StaffRole::SuperAdmin->value;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public_media')->url($this->avatar_path) : null;
    }

    public function roleName(?string $locale = null): string
    {
        $this->loadMissing('role');
        $role = $this->role;

        if ($role === null) {
            return '—';
        }

        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return $role->name_en ?: $role->name_ar;
        }

        return $role->name_ar ?: $role->name_en;
    }

    protected static function newFactory(): StaffUserFactory
    {
        return StaffUserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'session_version' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }
}
