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

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'last_login_at'])]
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
