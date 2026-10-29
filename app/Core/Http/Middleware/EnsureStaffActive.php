<?php

namespace App\Core\Http\Middleware;

use App\Modules\Staff\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof StaffUser) {
            return $next($request);
        }

        if (! $request->hasSession()) {
            return $next($request);
        }

        if (! $request->session()->has('staff_session_version')) {
            $request->session()->put('staff_session_version', (int) $user->session_version);
        }

        $stale = (int) $request->session()->get('staff_session_version') !== (int) $user->session_version;

        if ($user->is_active && ! $stale) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(Response::HTTP_FORBIDDEN, __('aqdar.auth.account_suspended'));
        }

        return redirect()
            ->route('dashboard')
            ->withErrors(['email' => __('aqdar.auth.account_suspended')]);
    }
}
