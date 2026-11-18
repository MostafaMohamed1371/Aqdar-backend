<?php

namespace App\Core\Http;

use App\Modules\Listing\Exceptions\RequiredDocumentsException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrorResponse
{
    public static function from(Throwable $exception): JsonResponse
    {
        [$status, $code, $message, $errors, $details] = self::payload($exception);

        $body = [
            'code' => $code,
            'title' => self::titleFor($code),
            'message' => $message,
            'hint' => self::hintFor($code),
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
            $body['details'] = $details ?? self::flattenErrors($errors);

            if ($details === null && count($body['details']) === 1) {
                $body['message'] = $body['details'][0];
            }
        }

        $response = response()->json($body, $status);

        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, list<string>>|null, 4: list<string>|null}
     */
    private static function payload(Throwable $exception): array
    {
        if ($exception instanceof RequiredDocumentsException) {
            $labels = $exception->labels();
            $code = $exception->reason === 'missing'
                ? 'documents_required'
                : 'documents_not_approved';

            return [
                $exception->status,
                $code,
                $exception->errors()['documents'][0] ?? __('aqdar.http.validation'),
                $exception->errors(),
                $labels !== [] ? $labels : null,
            ];
        }

        if ($exception instanceof ValidationException) {
            return [
                $exception->status,
                'validation',
                __('aqdar.http.validation'),
                $exception->errors(),
                null,
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [401, 'unauthenticated', __('aqdar.http.unauthenticated'), null, null];
        }

        if ($exception instanceof AuthorizationException) {
            $message = trim($exception->getMessage());

            return [403, 'forbidden', $message !== '' ? $message : __('aqdar.http.forbidden'), null, null];
        }

        if ($exception instanceof ModelNotFoundException) {
            return [404, 'not_found', __('aqdar.http.not_found'), null, null];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());
            $keepCustomMessage = in_array($status, [400, 403, 503], true) && $message !== '';
            $resolvedMessage = $keepCustomMessage ? $message : self::defaultMessage($status);
            $code = $resolvedMessage === __('aqdar.http.invalid_json')
                ? 'invalid_json'
                : self::codeForStatus($status);

            return [
                $status,
                $code,
                $resolvedMessage,
                null,
                null,
            ];
        }

        return [500, 'server_error', __('aqdar.http.server_error'), null, null];
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => __('aqdar.http.bad_request'),
            401 => __('aqdar.http.unauthenticated'),
            403 => __('aqdar.http.forbidden'),
            404 => __('aqdar.http.not_found'),
            405 => __('aqdar.http.method_not_allowed'),
            419 => __('aqdar.http.csrf'),
            422 => __('aqdar.http.validation'),
            429 => __('aqdar.http.too_many_requests'),
            503 => __('aqdar.http.maintenance'),
            default => $status >= 500
                ? __('aqdar.http.server_error')
                : __('aqdar.http.bad_request'),
        };
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'csrf',
            422 => 'validation',
            429 => 'too_many_requests',
            503 => 'maintenance',
            default => $status >= 500 ? 'server_error' : 'bad_request',
        };
    }

    private static function titleFor(string $code): string
    {
        return match ($code) {
            'validation' => __('aqdar.flash.validation_title'),
            'documents_required' => __('aqdar.listings.documents_missing_title'),
            'documents_not_approved' => __('aqdar.listings.documents_block_title'),
            'too_many_requests' => __('aqdar.http.too_many_requests'),
            'maintenance' => __('aqdar.http.maintenance'),
            default => __('aqdar.flash.error_title'),
        };
    }

    private static function hintFor(string $code): string
    {
        return match ($code) {
            'validation' => __('aqdar.flash.validation_hint'),
            'documents_required' => __('aqdar.listings.documents_missing_hint'),
            'documents_not_approved' => __('aqdar.listings.documents_block_hint'),
            'unauthenticated' => __('aqdar.http.hint_unauthenticated'),
            'forbidden' => __('aqdar.http.hint_forbidden'),
            'not_found' => __('aqdar.http.hint_not_found'),
            'too_many_requests' => __('aqdar.http.hint_too_many_requests'),
            'maintenance' => __('aqdar.http.hint_maintenance'),
            'invalid_json', 'bad_request' => __('aqdar.http.hint_bad_request'),
            'method_not_allowed' => __('aqdar.http.hint_bad_request'),
            'csrf' => __('aqdar.http.csrf'),
            default => __('aqdar.flash.error_hint'),
        };
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return list<string>
     */
    private static function flattenErrors(array $errors): array
    {
        $details = [];

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $trimmed = trim((string) $message);

                if ($trimmed !== '' && ! in_array($trimmed, $details, true)) {
                    $details[] = $trimmed;
                }
            }
        }

        return $details;
    }
}
