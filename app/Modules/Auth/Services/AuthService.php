<?php

namespace App\Modules\Auth\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Identity\ContactMask;
use App\Core\Identity\EgyptianPhone;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserService $userService,
        private OtpService $otpService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = $this->userService->register($this->applyConsent($data, requireConsent: true));
        $this->auditLogger->write('auth.registered', $user, $user, ['guard' => 'sanctum']);

        return $this->createAuthResponse($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userService->findByEmail($email);

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            $this->auditLogger->write('auth.login_failed', null, $user, [
                'email' => $email,
                'guard' => 'sanctum',
            ]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->userService->assertNotSuspended($user, 'email');
        $this->auditLogger->write('auth.login', $user, $user, ['guard' => 'sanctum']);

        return $this->createAuthResponse($user);
    }

    /**
     * @param  array{consent?: mixed, consent_privacy?: mixed, consent_terms?: mixed, locale?: string}  $consent
     * @return array{resend_after: int, consent_required: bool}
     */
    public function sendOtp(string $phone, array $consent = []): array
    {
        $normalized = EgyptianPhone::normalize($phone);
        $existing = $this->userService->findByPhone($normalized);
        $needsConsent = $existing === null
            || $existing->consent_privacy_at === null
            || $existing->consent_terms_at === null;
        $accepted = $this->consentAccepted($consent);
        $cached = Cache::has($this->consentCacheKey($normalized));

        if ($needsConsent && ! $accepted && ! $cached) {
            throw ValidationException::withMessages([
                'consent' => [__('aqdar.auth.consent_required')],
                'consent_privacy' => [__('aqdar.auth.consent_required')],
                'consent_terms' => [__('aqdar.auth.consent_required')],
            ]);
        }

        $this->otpService->send($phone);
        $ttl = max(1, (int) config('aqdar.otp.ttl_seconds', 300));

        if ($accepted) {
            Cache::put($this->consentCacheKey($normalized), [
                'accepted' => true,
                'locale' => is_string($consent['locale'] ?? null) ? $consent['locale'] : null,
            ], $ttl);
        }

        return [
            'resend_after' => max(1, (int) config('aqdar.otp.resend_after_seconds', 45)),
            'consent_required' => $needsConsent && ! $accepted && ! Cache::has($this->consentCacheKey($normalized)),
        ];
    }

    /**
     * @param  array{consent?: mixed, consent_privacy?: mixed, consent_terms?: mixed, locale?: string}  $consent
     * @return array{user: User, token: string}
     */
    public function verifyOtp(string $phone, string $code, array $consent = []): array
    {
        $normalized = $this->otpService->verify($phone, $code);
        $cached = Cache::pull($this->consentCacheKey($normalized));
        if (is_array($cached) && ! empty($cached['accepted'])) {
            $consent['consent'] = true;
            if (! isset($consent['locale']) && is_string($cached['locale'] ?? null)) {
                $consent['locale'] = $cached['locale'];
            }
        }
        $existing = $this->userService->findByPhone($normalized);

        if ($existing === null || $existing->consent_privacy_at === null || $existing->consent_terms_at === null) {
            if (! $this->consentAccepted($consent)) {
                throw ValidationException::withMessages([
                    'consent' => [__('aqdar.auth.consent_required')],
                    'consent_privacy' => [__('aqdar.auth.consent_required')],
                    'consent_terms' => [__('aqdar.auth.consent_required')],
                ]);
            }
        }

        $user = $this->userService->findOrCreateByVerifiedPhone($normalized, $this->applyConsent($consent, requireConsent: false));
        $this->userService->assertNotSuspended($user, 'phone');
        $this->auditLogger->write('auth.otp_verified', $user, $user, [
            'phone' => ContactMask::phone($normalized),
        ]);

        return $this->createAuthResponse($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function webLogin(string $email, string $password, bool $remember = false): StaffUser
    {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->auditLogger->write('auth.login_failed', null, null, [
                'email' => $email,
                'guard' => 'web',
            ]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var StaffUser $staff */
        $staff = Auth::guard('web')->user();

        if (! $staff->is_active) {
            Auth::guard('web')->logout();
            $this->auditLogger->write('auth.login_failed', $staff, $staff, [
                'email' => $email,
                'guard' => 'web',
                'reason' => 'inactive',
            ]);

            throw ValidationException::withMessages([
                'email' => [__('aqdar.auth.account_suspended')],
            ]);
        }

        $staff->forceFill(['last_login_at' => now()])->save();
        request()->session()->put('staff_session_version', (int) $staff->session_version);
        $this->auditLogger->write('auth.login', $staff, $staff, [
            'email' => $email,
            'guard' => 'web',
        ]);

        return $staff;
    }

    public function webLogout(): void
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function forgotPassword(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    public function resetPassword(array $data): string
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function createAuthResponse(User $user): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = is_numeric($minutes) ? now()->addMinutes((int) $minutes) : null;
        $token = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyConsent(array $data, bool $requireConsent): array
    {
        $accepted = $this->consentAccepted($data);

        if ($requireConsent && ! $accepted) {
            throw ValidationException::withMessages([
                'consent_privacy' => [__('aqdar.auth.consent_required')],
                'consent_terms' => [__('aqdar.auth.consent_required')],
            ]);
        }

        if ($accepted) {
            $data['consent_privacy_at'] = now();
            $data['consent_terms_at'] = now();
            $data['consent_version'] = (string) config('aqdar.consent.version', '1');
        }

        $locales = config('aqdar.locales', ['ar', 'en']);
        if (isset($data['locale']) && is_string($data['locale']) && in_array($data['locale'], $locales, true)) {
            $data['locale'] = $data['locale'];
        } else {
            unset($data['locale']);
        }

        unset($data['consent'], $data['consent_privacy'], $data['consent_terms'], $data['password_confirmation']);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function consentAccepted(array $data): bool
    {
        if ($this->isAccepted($data['consent'] ?? null)) {
            return true;
        }

        return $this->isAccepted($data['consent_privacy'] ?? null)
            && $this->isAccepted($data['consent_terms'] ?? null);
    }

    private function consentCacheKey(string $phone): string
    {
        return 'otp:consent:'.$phone;
    }

    private function isAccepted(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
