<?php

namespace App\Modules\Staff\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Enums\StaffRole;
use App\Modules\Staff\Models\Permission;
use App\Modules\Staff\Models\Role;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function paginate(string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        return StaffUser::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('branch', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Role> */
    public function roles(): Collection
    {
        return Role::query()->with('permissions')->orderBy('id')->get();
    }

    /** @return Collection<int, Permission> */
    public function permissions(): Collection
    {
        return Permission::query()->orderBy('id')->get();
    }

    /** @return array<string, array<string, bool>> */
    public function permissionMatrix(): array
    {
        $roles = $this->roles()->keyBy('slug');
        $matrix = [];

        foreach (PermissionEnum::cases() as $permission) {
            $row = [];

            foreach (StaffRole::cases() as $staffRole) {
                $role = $roles->get($staffRole->value);
                $row[$staffRole->value] = $role?->permissions->contains('slug', $permission->value) ?? false;
            }

            $matrix[$permission->value] = $row;
        }

        return $matrix;
    }

    /** @param array{name: string, email: string, password: string, role_id: int} $data */
    public function create(array $data, StaffUser $actor): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'],
            'branch' => $this->normalizeBranch($data['branch'] ?? null),
            'is_active' => true,
        ]);

        $this->auditLogger->write('staff.created', $actor, $staff, [
            'role_id' => $staff->role_id,
        ]);

        return $staff;
    }

    /** @param array{name: string, email: string, password?: string|null, role_id: int, branch?: string|null} $data */
    public function update(StaffUser $staff, array $data, StaffUser $actor): StaffUser
    {
        $this->assertNotLastSuperAdminRoleChange($staff, (int) $data['role_id']);

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        if (array_key_exists('branch', $data)) {
            $data['branch'] = $this->normalizeBranch($data['branch']);
        }

        $roleChanged = array_key_exists('role_id', $data) && (int) $data['role_id'] !== (int) $staff->role_id;
        $passwordChanged = array_key_exists('password', $data);

        $staff->fill($data);
        $staff->save();

        if ($roleChanged || $passwordChanged) {
            $this->invalidateSessions($staff);
        }

        $this->auditLogger->write('staff.updated', $actor, $staff, [
            'role_id' => $staff->role_id,
        ]);

        return $staff;
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role_id?: int|null}  $data
     */
    public function updateOwnProfile(StaffUser $staff, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): StaffUser
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        $passwordChanged = filled($data['password'] ?? null);
        $roleChanged = false;

        if ($passwordChanged) {
            $payload['password'] = $data['password'];
        }

        if ($staff->hasPermission(PermissionEnum::ManageStaff) && array_key_exists('role_id', $data) && $data['role_id'] !== null) {
            $roleId = (int) $data['role_id'];
            $this->assertNotLastSuperAdminRoleChange($staff, $roleId);
            $roleChanged = $roleId !== (int) $staff->role_id;
            $payload['role_id'] = $roleId;
        }

        $photoChanged = false;

        if ($photo !== null) {
            $this->deleteAvatar($staff->avatar_path);
            $payload['avatar_path'] = $photo->store('staff-avatars', 'public_media');
            $photoChanged = true;
        } elseif ($removePhoto) {
            $this->deleteAvatar($staff->avatar_path);
            $payload['avatar_path'] = null;
            $photoChanged = true;
        }

        $staff->fill($payload);
        $staff->save();

        if ($passwordChanged || $roleChanged) {
            $this->invalidateSessions($staff);
        }

        $this->auditLogger->write('staff.profile_updated', $staff, $staff, [
            'password_changed' => $passwordChanged,
            'photo_changed' => $photoChanged,
            'role_changed' => $roleChanged,
        ]);

        return $staff;
    }

    public function delete(StaffUser $staff, StaffUser $actor): void
    {
        if ($staff->is($actor)) {
            throw ValidationException::withMessages([
                'staff' => [__('aqdar.staff.cannot_delete_self')],
            ]);
        }

        $this->assertNotLastSuperAdmin($staff);

        $this->auditLogger->write('staff.deleted', $actor, $staff, [
            'email' => $staff->email,
        ]);

        $this->deleteAvatar($staff->avatar_path);
        $staff->delete();
    }

    public function deactivate(StaffUser $staff, StaffUser $actor, ?string $reason = null): void
    {
        if ($staff->is($actor)) {
            throw ValidationException::withMessages([
                'staff' => [__('aqdar.staff.cannot_deactivate_self')],
            ]);
        }

        $staff->update(['is_active' => false]);
        $this->invalidateSessions($staff);

        $this->auditLogger->write('staff.deactivated', $actor, $staff, array_filter(['reason' => $reason]));
    }

    public function signOutEverywhere(StaffUser $staff, StaffUser $actor, string $reason): void
    {
        $this->invalidateSessions($staff);

        $this->auditLogger->write('staff.sessions_revoked', $actor, $staff, ['reason' => $reason]);
    }

    public function activate(StaffUser $staff, StaffUser $actor): void
    {
        $staff->update(['is_active' => true]);

        $this->auditLogger->write('staff.activated', $actor, $staff);
    }

    public function stats(): array
    {
        return [
            'total' => StaffUser::query()->count(),
            'sales' => StaffUser::query()->whereHas('role', fn ($query) => $query->where('slug', StaffRole::Sales->value))->count(),
            'admin' => StaffUser::query()->whereHas('role', fn ($query) => $query->whereIn('slug', [
                StaffRole::SuperAdmin->value,
                StaffRole::Operations->value,
            ]))->count(),
            'active' => StaffUser::query()->where('is_active', true)->count(),
            'branches' => StaffUser::query()->whereNotNull('branch')->distinct()->count('branch'),
        ];
    }

    /** @return list<string> */
    public function branches(): array
    {
        return StaffUser::query()->whereNotNull('branch')->distinct()->orderBy('branch')->pluck('branch')->all();
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path) {
            Storage::disk('public_media')->delete($path);
        }
    }

    private function normalizeBranch(mixed $branch): ?string
    {
        $branch = trim((string) $branch);

        return $branch === '' ? null : $branch;
    }

    private function assertNotLastSuperAdmin(StaffUser $staff): void
    {
        $staff->loadMissing('role');

        if ($staff->isSuperAdmin() && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'staff' => [__('aqdar.staff.cannot_delete_last_super_admin')],
            ]);
        }
    }

    private function assertNotLastSuperAdminRoleChange(StaffUser $staff, int $newRoleId): void
    {
        $staff->loadMissing('role');

        if (! $staff->isSuperAdmin() || $staff->role_id === $newRoleId) {
            return;
        }

        if ($this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'role_id' => [__('aqdar.staff.cannot_delete_last_super_admin')],
            ]);
        }
    }

    private function invalidateSessions(StaffUser $staff): void
    {
        $staff->forceFill([
            'session_version' => ((int) $staff->session_version) + 1,
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function superAdminCount(): int
    {
        return StaffUser::query()
            ->whereHas('role', fn ($query) => $query->where('slug', StaffRole::SuperAdmin->value))
            ->count();
    }
}
