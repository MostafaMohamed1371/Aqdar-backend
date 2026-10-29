<?php

namespace App\Core\Http;

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
        [$status, $message, $errors] = self::payload($exception);

        $body = ['message' => $message];

        if ($errors !== null) {
            $body['errors'] = $errors;
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
     * @return array{0: int, 1: string, 2: array<string, list<string>>|null}
     */
    private static function payload(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                $exception->status,
                __('aqdar.http.validation'),
                $exception->errors(),
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [401, __('aqdar.http.unauthenticated'), null];
        }

        if ($exception instanceof AuthorizationException) {
            $message = trim($exception->getMessage());

            return [403, $message !== '' ? $message : __('aqdar.http.forbidden'), null];
        }

        if ($exception instanceof ModelNotFoundException) {
            return [404, __('aqdar.http.not_found'), null];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());
            $keepCustomMessage = in_array($status, [400, 403, 503], true) && $message !== '';

            return [
                $status,
                $keepCustomMessage ? $message : self::defaultMessage($status),
                null,
            ];
        }

        return [500, __('aqdar.http.server_error'), null];
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
}
