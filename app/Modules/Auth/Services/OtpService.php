<?php

namespace App\Modules\Auth\Services;

use App\Core\Identity\EgyptianPhone;
use App\Modules\Auth\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class OtpService
{
    public function __construct(private SmsGateway $sms) {}

    public function send(string $phone): void
    {
        $normalized = EgyptianPhone::normalize($phone);
        $code = $this->generateCode();
        $minutes = max(1, (int) ceil(((int) config('aqdar.otp.ttl_seconds', 300)) / 60));
        $message = __('aqdar.auth.otp_sms', [
            'code' => $code,
            'minutes' => $minutes,
        ]);

        try {
            $this->sms->send($normalized, $message);
        } catch (Throwable $exception) {
            Log::error('aqdar.sms.failed', [
                'phone' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(503, __('aqdar.auth.otp_delivery_failed'));
        }

        Cache::put($this->cacheKey($normalized), Hash::make($code), config('aqdar.otp.ttl_seconds', 300));
        Cache::forget($this->attemptsKey($normalized));

        if (app()->environment('local')) {
            Log::debug('aqdar.otp.generated', [
                'phone' => $normalized,
                'code' => $code,
            ]);
        }
    }

    public function verify(string $phone, string $code): string
    {
        $normalized = EgyptianPhone::normalize($phone);
        $hashed = Cache::get($this->cacheKey($normalized));

        if (! is_string($hashed) || ! Hash::check($code, $hashed)) {
            $this->hitVerifyAttempt($normalized);

            throw ValidationException::withMessages([
                'code' => [__('aqdar.auth.otp_invalid')],
            ]);
        }

        Cache::forget($this->cacheKey($normalized));
        Cache::forget($this->attemptsKey($normalized));

        return $normalized;
    }

    private function generateCode(): string
    {
        return (string) config('aqdar.otp.test_code', '1234');
    }

    private function hitVerifyAttempt(string $phone): void
    {
        $key = $this->attemptsKey($phone);
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, config('aqdar.otp.ttl_seconds', 300));

        if ($attempts >= (int) config('aqdar.otp.max_attempts', 5)) {
            Cache::forget($this->cacheKey($phone));
        }
    }

    private function cacheKey(string $phone): string
    {
        return 'otp:code:'.$phone;
    }

    private function attemptsKey(string $phone): string
    {
        return 'otp:attempts:'.$phone;
    }
}
