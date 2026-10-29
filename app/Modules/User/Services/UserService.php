<?php

namespace App\Modules\User\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\AccountType;
use App\Core\Enums\IdentityStatus;
use App\Core\Identity\EgyptianPhone;
use App\Core\Services\AbstractService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserDevice;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService extends AbstractService
{
    public function __construct(
        UserRepository $repository,
        private AuditLogger $auditLogger,
    ) {
        parent::__construct($repository);
    }

    public function register(array $data): User
    {
        if (isset($data['phone'])) {
            $data['phone'] = EgyptianPhone::normalize($data['phone']);
        }

        $data['is_buyer'] = $data['is_buyer'] ?? true;
        $data['account_type'] = $data['account_type'] ?? AccountType::Individual->value;
        $data['identity_status'] = $data['identity_status'] ?? IdentityStatus::Unverified->value;

        /** @var User $user */
        $user = $this->repository->create($data);

        return $user;
    }

    public function createByAdmin(array $data, StaffUser $actor): User
    {
        $user = $this->register($data);

        $this->auditLogger->write('user.created', $actor, $user);

        return $user;
    }

    public function updateByAdmin(User $user, array $data, StaffUser $actor): User
    {
        if (array_key_exists('phone', $data) && is_string($data['phone']) && $data['phone'] !== '') {
            $data['phone'] = EgyptianPhone::normalize($data['phone']);
        }

        if (array_key_exists('phone', $data) && ($data['phone'] === null || $data['phone'] === '')) {
            $data['phone'] = null;
        }

        $user->fill($data);
        $user->save();

        $this->auditLogger->write('user.updated', $actor, $user);

        return $user;
    }

    public function deleteByAdmin(User $user, StaffUser $actor): void
    {
        $this->auditLogger->write('user.deleted', $actor, $user, [
            'name' => $user->name,
        ]);

        $user->tokens()->delete();
        $user->devices()->delete();
        $user->delete();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->repository->findByPhone($phone);
    }

    /** @param  array<string, mixed>  $extra */
    public function findOrCreateByVerifiedPhone(string $phone, array $extra = []): User
    {
        $user = $this->findByPhone($phone);

        if ($user !== null) {
            $updates = [];
            if ($user->phone_verified_at === null) {
                $updates['phone_verified_at'] = now();
            }
            if ($user->consent_privacy_at === null && isset($extra['consent_privacy_at'])) {
                $updates['consent_privacy_at'] = $extra['consent_privacy_at'];
            }
            if ($user->consent_terms_at === null && isset($extra['consent_terms_at'])) {
                $updates['consent_terms_at'] = $extra['consent_terms_at'];
            }
            if ($user->consent_version === null && isset($extra['consent_version'])) {
                $updates['consent_version'] = $extra['consent_version'];
            }
            if (isset($extra['locale']) && is_string($extra['locale'])) {
                $updates['locale'] = $extra['locale'];
            }
            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            return $user;
        }

        return $this->register(array_merge([
            'name' => 'مستخدم '.substr($phone, -4),
            'phone' => $phone,
            'phone_verified_at' => now(),
            'is_buyer' => true,
            'is_seller' => false,
            'account_type' => AccountType::Individual->value,
            'identity_status' => IdentityStatus::Unverified->value,
            'locale' => 'ar',
        ], $extra));
    }

    public function paginateForAdmin(string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        return [
            'total' => User::query()->count(),
            'individuals' => User::query()->where('account_type', AccountType::Individual->value)->count(),
            'developers' => User::query()->where('account_type', AccountType::Developer->value)->count(),
            'brokers' => User::query()->where('account_type', AccountType::Broker->value)->count(),
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        $firstEmail = null;
        if ($user->email === null && isset($data['email']) && is_string($data['email']) && $data['email'] !== '') {
            $firstEmail = $data['email'];
        }

        unset(
            $data['phone'],
            $data['email'],
            $data['password'],
            $data['identity_status'],
            $data['consent_privacy_at'],
            $data['consent_terms_at'],
            $data['consent_version'],
        );

        $user->fill($data);

        if ($firstEmail !== null) {
            $user->forceFill([
                'email' => $firstEmail,
                'email_verified_at' => null,
            ]);
        }

        $user->save();

        return $user;
    }

    public function registerDevice(User $user, array $data): UserDevice
    {
        return UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_uuid' => $data['device_uuid'],
            ],
            [
                'platform' => $data['platform'] ?? null,
                'push_token' => $data['push_token'] ?? null,
                'last_seen_at' => now(),
            ],
        );
    }

    public function suspend(User $user, string $reason, StaffUser $actor): void
    {
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => [__('aqdar.users.reason_required')],
            ]);
        }

        $user->forceFill([
            'suspended_at' => now(),
            'suspend_reason' => $reason,
        ])->save();

        $user->tokens()->delete();

        $this->auditLogger->write('user.suspended', $actor, $user, [
            'reason' => $reason,
        ]);
    }

    public function reactivate(User $user, StaffUser $actor): void
    {
        $user->forceFill([
            'suspended_at' => null,
            'suspend_reason' => null,
        ])->save();

        $this->auditLogger->write('user.reactivated', $actor, $user);
    }

    public function revealPhone(User $user, StaffUser $actor): string
    {
        $this->auditLogger->access('user.phone_revealed', $actor, $user, [
            'phone_masked' => true,
        ]);

        return $user->phone ?? '';
    }

    public function assertNotSuspended(User $user, string $field = 'email'): void
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                $field => [__('aqdar.auth.account_suspended')],
            ]);
        }
    }
}
