<?php

namespace App\Core\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('aqdar.auth.require_profile', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || $user->isProfileComplete()) {
            return $next($request);
        }

        throw new HttpResponseException(response()->json([
            'code' => 'profile_incomplete',
            'title' => __('aqdar.flash.error_title'),
            'message' => __('aqdar.auth.profile_incomplete'),
            'hint' => __('aqdar.auth.profile_incomplete_hint'),
            'errors' => [
                'name' => [__('aqdar.auth.profile_incomplete')],
            ],
            'details' => [__('aqdar.auth.profile_incomplete')],
        ], Response::HTTP_UNPROCESSABLE_ENTITY)->header('Content-Language', app()->getLocale()));
    }
}
