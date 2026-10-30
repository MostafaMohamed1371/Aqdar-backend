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

        if ($user instanceof User && $user->isMarketplaceBlocked()) {
            abort(Response::HTTP_FORBIDDEN, $this->blockedMessage($user));
        }

        return $next($request);
    }

    private function blockedMessage(User $user): string
    {
        if ($user->isClosed()) {
            return __('aqdar.auth.account_closed');
        }

        if ($user->isClosePending()) {
            return __('aqdar.auth.account_close_pending');
        }

        return __('aqdar.auth.account_suspended');
    }
}
