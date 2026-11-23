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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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
     * Returns a token, or — when the account has two-factor enabled — an OTP challenge to finish with completeTwoFactorLogin().
     *
     * @return array{user: User, token: string}|array{two_factor_required: true, challenge: string, phone: string, resend_after: int}
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

        if ($user->two_factor_enabled && $user->phone !== null) {
            return $this->startTwoFactorChallenge($user);
        }

        $this->auditLogger->write('auth.login', $user, $user, ['guard' => 'sanctum']);

        return $this->createAuthResponse($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function completeTwoFactorLogin(string $challenge, string $code): array
    {
        $userId = Cache::get($this->twoFactorCacheKey($challenge));
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user === null || $user->phone === null) {
            throw ValidationException::withMessages([
                'challenge' => [__('aqdar.auth.two_factor_expired')],
            ]);
        }

        $this->otpService->verify($user->phone, $code);
        Cache::forget($this->twoFactorCacheKey($challenge));
        $this->userService->assertNotSuspended($user, 'email');
        $this->auditLogger->write('auth.login', $user, $user, ['guard' => 'sanctum', 'two_factor' => true]);

        return $this->createAuthResponse($user);
    }

    public function setTwoFactor(User $user, bool $enabled): User
    {
        if ($enabled && ($user->phone === null || $user->phone_verified_at === null)) {
            throw ValidationException::withMessages([
                'enabled' => [__('aqdar.auth.two_factor_needs_phone')],
            ]);
        }

        $user->forceFill(['two_factor_enabled' => $enabled])->save();
        $this->auditLogger->write($enabled ? 'auth.two_factor_enabled' : 'auth.two_factor_disabled', $user, $user, ['guard' => 'sanctum']);

        return $user;
    }

    /**
     * @return array{two_factor_required: true, challenge: string, phone: string, resend_after: int}
     */
    private function startTwoFactorChallenge(User $user): array
    {
        $this->otpService->send($user->phone);
        $challenge = Str::random(64);
        Cache::put($this->twoFactorCacheKey($challenge), $user->id, max(1, (int) config('aqdar.otp.ttl_seconds', 300)));
        $this->auditLogger->write('auth.two_factor_challenged', $user, $user, ['guard' => 'sanctum']);

        return [
            'two_factor_required' => true,
            'challenge' => $challenge,
            'phone' => ContactMask::phone($user->phone),
            'resend_after' => max(1, (int) config('aqdar.otp.resend_after_seconds', 45)),
        ];
    }

    private function twoFactorCacheKey(string $challenge): string
    {
        return 'aqdar:auth:2fa:'.hash('sha256', $challenge);
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

        if ($existing !== null) {
            $this->userService->assertNotSuspended($existing, 'phone');
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

    /**
     * Returns the signed-in staff member, or null when a one-time code was emailed and must be verified first.
     */
    public function webLogin(string $email, string $password, bool $remember = false): ?StaffUser
    {
        $twoFactor = (bool) config('aqdar.staff.two_factor', false);

        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $twoFactor ? false : $remember)) {
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

        if ($twoFactor) {
            Auth::guard('web')->logout();
            $this->startStaffTwoFactorChallenge($staff, $remember);

            return null;
        }

        $this->finishWebLogin($staff, $email);

        return $staff;
    }

    public function hasPendingStaffTwoFactor(): bool
    {
        $challenge = request()->session()->get('staff_two_factor_challenge');

        return is_string($challenge) && Cache::has($this->staffTwoFactorCacheKey($challenge));
    }

    public function completeStaffTwoFactorLogin(string $code): StaffUser
    {
        $session = request()->session();
        $challenge = $session->get('staff_two_factor_challenge');
        $state = is_string($challenge) ? Cache::get($this->staffTwoFactorCacheKey($challenge)) : null;
        $staff = is_array($state) ? StaffUser::query()->find($state['staff_id'] ?? null) : null;

        if (! is_array($state) || $staff === null || ! $staff->is_active) {
            $session->forget('staff_two_factor_challenge');

            throw ValidationException::withMessages([
                'code' => [__('aqdar.auth.two_factor_expired')],
            ]);
        }

        $key = $this->staffTwoFactorCacheKey($challenge);
        $maxAttempts = max(1, (int) config('aqdar.otp.max_attempts', 5));

        if ((int) ($state['attempts'] ?? 0) >= $maxAttempts) {
            Cache::forget($key);
            $session->forget('staff_two_factor_challenge');

            throw ValidationException::withMessages([
                'code' => [__('aqdar.auth.staff_two_factor_locked')],
            ]);
        }

        if (! hash_equals((string) $state['code_hash'], $this->staffTwoFactorHash($challenge, $code))) {
            $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
            Cache::put($key, $state, max(1, (int) config('aqdar.otp.ttl_seconds', 300)));
            $this->auditLogger->write('auth.login_failed', $staff, $staff, [
                'email' => $staff->email,
                'guard' => 'web',
                'reason' => 'two_factor_invalid',
            ]);

            throw ValidationException::withMessages([
                'code' => [__('aqdar.auth.staff_two_factor_invalid')],
            ]);
        }

        Cache::forget($key);
        $session->forget('staff_two_factor_challenge');
        $session->regenerate();
        Auth::guard('web')->login($staff, (bool) ($state['remember'] ?? false));
        $this->finishWebLogin($staff, $staff->email, twoFactor: true);

        return $staff;
    }

    private function startStaffTwoFactorChallenge(StaffUser $staff, bool $remember): void
    {
        $challenge = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = max(1, (int) config('aqdar.otp.ttl_seconds', 300));

        Cache::put($this->staffTwoFactorCacheKey($challenge), [
            'staff_id' => $staff->id,
            'code_hash' => $this->staffTwoFactorHash($challenge, $code),
            'remember' => $remember,
            'attempts' => 0,
        ], $ttl);
        request()->session()->put('staff_two_factor_challenge', $challenge);

        Mail::raw(
            __('aqdar.auth.staff_two_factor_body', ['code' => $code, 'minutes' => (int) ceil($ttl / 60)]),
            fn ($message) => $message->to($staff->email)->subject(__('aqdar.auth.staff_two_factor_subject')),
        );

        $this->auditLogger->write('auth.two_factor_challenged', $staff, $staff, ['guard' => 'web']);
    }

    private function finishWebLogin(StaffUser $staff, string $email, bool $twoFactor = false): void
    {
        $staff->forceFill(['last_login_at' => now()])->save();
        request()->session()->put('staff_session_version', (int) $staff->session_version);
        $this->auditLogger->write('auth.login', $staff, $staff, [
            'email' => $email,
            'guard' => 'web',
            'two_factor' => $twoFactor,
        ]);
    }

    private function staffTwoFactorCacheKey(string $challenge): string
    {
        return 'aqdar:staff:2fa:'.hash('sha256', $challenge);
    }

    private function staffTwoFactorHash(string $challenge, string $code): string
    {
        return hash_hmac('sha256', $code, $challenge);
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

    /**
     * Sets a new password and signs the account out of every other device.
     */
    public function changePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();

        $token = $user->currentAccessToken();
        $currentTokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        $this->auditLogger->write('auth.password_changed', $user, $user, ['guard' => 'sanctum']);
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
