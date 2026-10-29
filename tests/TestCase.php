<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @param  array<string, mixed>  $extra */
    protected function otpConsent(array $extra = []): array
    {
        return array_merge([
            'consent_privacy' => true,
            'consent_terms' => true,
        ], $extra);
    }

    /** @param  array<string, mixed>  $extra */
    protected function otpSendBody(string $phone, array $extra = []): array
    {
        return array_merge([
            'phone' => $phone,
            'consent' => true,
        ], $extra);
    }
}
