<?php

namespace App\Modules\User\Models;

use App\Core\Enums\AccountType;
use App\Core\Enums\IdentityStatus;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Listing\Models\Favorite;
use App\Modules\Listing\Models\Listing;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\User\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'phone_verified_at',
    'account_type',
    'is_buyer',
    'is_seller',
    'identity_status',
    'suspended_at',
    'suspend_reason',
    'locale',
    'consent_privacy_at',
    'consent_terms_at',
    'consent_version',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function notificationPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function activityLabel(): string
    {
        return match (true) {
            $this->is_buyer && $this->is_seller => 'مشتري + بائع',
            $this->is_seller => 'بائع',
            default => 'مشتري',
        };
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'identity_status' => IdentityStatus::class,
            'is_buyer' => 'boolean',
            'is_seller' => 'boolean',
            'suspended_at' => 'datetime',
            'consent_privacy_at' => 'datetime',
            'consent_terms_at' => 'datetime',
        ];
    }
}
