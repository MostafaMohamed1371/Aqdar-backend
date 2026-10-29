<?php

namespace App\Core\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            abort(Response::HTTP_FORBIDDEN, __('aqdar.auth.account_suspended'));
        }

        return $next($request);
    }
}
