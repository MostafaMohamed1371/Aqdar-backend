<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        if (! $request->bearerToken()) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, __('auth.unauthenticated'));
        }

        Auth::guard('sanctum')->setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
