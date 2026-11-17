<?php

namespace Tests\Unit;

use App\Core\Http\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiErrorResponseShapeTest extends TestCase
{
    public function test_validation_error_includes_readable_title_hint_and_details(): void
    {
        app()->setLocale('ar');

        $exception = ValidationException::withMessages([
            'contact_method' => [__('validation.required', ['attribute' => __('aqdar.requests.contact_method')])],
            'consent' => [__('aqdar.auth.consent_required')],
        ]);

        $response = ApiErrorResponse::from($exception);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('validation', $payload['code']);
        $this->assertSame(__('aqdar.flash.validation_title', locale: 'ar'), $payload['title']);
        $this->assertSame(__('aqdar.flash.validation_hint', locale: 'ar'), $payload['hint']);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertArrayHasKey('contact_method', $payload['errors']);
        $this->assertSame($payload['details'], array_values(array_unique([
            $payload['errors']['contact_method'][0],
            $payload['errors']['consent'][0],
        ])));
        $this->assertStringContainsString('وسيلة التواصل المفضلة', $payload['errors']['contact_method'][0]);
        $this->assertStringNotContainsString('contact method', strtolower($payload['errors']['contact_method'][0]));
    }

    public function test_single_field_validation_uses_that_message_as_message(): void
    {
        app()->setLocale('en');

        $message = 'Preferred contact is required.';
        $payload = ApiErrorResponse::from(
            ValidationException::withMessages(['contact_method' => [$message]])
        )->getData(true);

        $this->assertSame($message, $payload['message']);
        $this->assertSame([$message], $payload['details']);
    }

    public function test_auth_error_includes_code_title_and_hint(): void
    {
        app()->setLocale('en');

        $payload = ApiErrorResponse::from(new AuthenticationException)->getData(true);

        $this->assertSame('unauthenticated', $payload['code']);
        $this->assertSame(__('aqdar.flash.error_title', locale: 'en'), $payload['title']);
        $this->assertSame(__('aqdar.http.unauthenticated', locale: 'en'), $payload['message']);
        $this->assertSame(__('aqdar.http.hint_unauthenticated', locale: 'en'), $payload['hint']);
        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertArrayNotHasKey('details', $payload);
    }

    public function test_http_exception_keeps_custom_503_message(): void
    {
        app()->setLocale('en');

        $payload = ApiErrorResponse::from(
            new HttpException(503, __('aqdar.auth.otp_delivery_failed', locale: 'en'))
        )->getData(true);

        $this->assertSame('maintenance', $payload['code']);
        $this->assertSame(__('aqdar.auth.otp_delivery_failed', locale: 'en'), $payload['message']);
    }
}
