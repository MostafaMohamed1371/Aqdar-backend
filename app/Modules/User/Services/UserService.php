<?php

namespace App\Modules\User\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\AccountType;
use App\Core\Enums\IdentityStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Identity\EgyptianPhone;
use App\Core\Services\AbstractService;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingStatusHistory;
use App\Modules\Listing\Services\ListingStateMachine;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserDevice;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
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

    public function revealEmail(User $user, StaffUser $actor): string
    {
        $this->auditLogger->access('user.email_revealed', $actor, $user, [
            'email_masked' => true,
        ]);

        return $user->email ?? '';
    }

    /**
     * Flags accounts that share a device with another account or whose email differs from another only by case.
     *
     * @param  iterable<User>  $users
     * @return array<int, list<string>> user id => reasons ("device", "email")
     */
    public function possibleDuplicates(iterable $users): array
    {
        $users = collect($users);
        if ($users->isEmpty()) {
            return [];
        }

        $flags = [];

        $pageDevices = UserDevice::query()->whereIn('user_id', $users->pluck('id'))->get(['user_id', 'device_uuid']);
        $sharedUuids = UserDevice::query()
            ->whereIn('device_uuid', $pageDevices->pluck('device_uuid')->unique())
            ->get(['user_id', 'device_uuid'])
            ->groupBy('device_uuid')
            ->filter(fn ($rows) => $rows->pluck('user_id')->unique()->count() > 1)
            ->keys();
        foreach ($pageDevices->whereIn('device_uuid', $sharedUuids)->pluck('user_id')->unique() as $userId) {
            $flags[(int) $userId][] = 'device';
        }

        $emails = $users->pluck('email')->filter()->map(fn (string $email) => mb_strtolower($email))->unique();
        if ($emails->isNotEmpty()) {
            $emailCounts = User::query()
                ->where(function ($query) use ($emails): void {
                    foreach ($emails as $email) {
                        $query->orWhere('email', 'ilike', addcslashes($email, '%_\\'));
                    }
                })
                ->pluck('email')
                ->countBy(fn (string $email) => mb_strtolower($email));

            foreach ($users as $user) {
                if ($user->email && ($emailCounts[mb_strtolower($user->email)] ?? 0) > 1) {
                    $flags[$user->id][] = 'email';
                }
            }
        }

        return $flags;
    }

    public function requestClose(User $user, string $reason): User
    {
        if ($user->isClosed()) {
            throw ValidationException::withMessages([
                'reason' => [__('aqdar.users.already_closed')],
            ]);
        }

        if (! $user->isClosePending()) {
            $user->forceFill([
                'close_requested_at' => now(),
                'close_reason' => $reason,
            ])->save();
            $this->archivePublicListings($user);
            $this->auditLogger->write('user.closure_requested', $user, $user, [
                'reason' => $reason,
            ]);
        }

        $user->tokens()->delete();
        $user->devices()->delete();

        return $user->fresh() ?? $user;
    }

    public function completeClose(User $user, StaffUser $actor): User
    {
        if ($user->isClosed()) {
            throw ValidationException::withMessages([
                'user' => [__('aqdar.users.already_closed')],
            ]);
        }

        if (! $user->isClosePending()) {
            throw ValidationException::withMessages([
                'user' => [__('aqdar.users.no_pending_close')],
            ]);
        }

        $this->archivePublicListings($user);
        $user->savedSearches()->delete();
        $user->tokens()->delete();
        $user->devices()->delete();

        $user->forceFill([
            'name' => 'حساب مغلق',
            'email' => 'closed.'.$user->id.'@invalid.local',
            'phone' => null,
            'phone_verified_at' => null,
            'email_verified_at' => null,
            'password' => Str::password(32),
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ])->save();

        $this->auditLogger->write('user.closure_completed', $actor, $user, [
            'anonymized' => true,
        ]);

        return $user->fresh() ?? $user;
    }

    public function declineClose(User $user, StaffUser $actor): User
    {
        if ($user->isClosed()) {
            throw ValidationException::withMessages([
                'user' => [__('aqdar.users.already_closed')],
            ]);
        }

        if (! $user->isClosePending()) {
            throw ValidationException::withMessages([
                'user' => [__('aqdar.users.no_pending_close')],
            ]);
        }

        $user->forceFill([
            'close_requested_at' => null,
            'close_reason' => null,
        ])->save();

        $this->auditLogger->write('user.closure_declined', $actor, $user);

        return $user->fresh() ?? $user;
    }

    public function assertNotSuspended(User $user, string $field = 'email'): void
    {
        if ($user->isClosed()) {
            throw ValidationException::withMessages([
                $field => [__('aqdar.auth.account_closed')],
            ]);
        }

        if ($user->isClosePending()) {
            throw ValidationException::withMessages([
                $field => [__('aqdar.auth.account_close_pending')],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                $field => [__('aqdar.auth.account_suspended')],
            ]);
        }
    }

    private function archivePublicListings(User $user): void
    {
        $machine = app(ListingStateMachine::class);

        Listing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ListingStatus::publicCases())
            ->get()
            ->each(function (Listing $listing) use ($user, $machine): void {
                if (! $machine->canTransition($listing->status, ListingStatus::Archived)) {
                    return;
                }

                $from = $listing->status;
                $listing->forceFill(['status' => ListingStatus::Archived])->save();
                ListingStatusHistory::query()->create([
                    'listing_id' => $listing->id,
                    'from_status' => $from,
                    'to_status' => ListingStatus::Archived,
                    'actor_type' => $user->getMorphClass(),
                    'actor_id' => $user->id,
                    'reason' => 'account_closure',
                ]);
            });
    }
}
